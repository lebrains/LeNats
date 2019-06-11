<?php

namespace LeNats\Subscription;

use Exception;
use JMS\Serializer\SerializerInterface;
use LeNats\Events\CloudEvent;
use LeNats\Exceptions\StreamException;
use LeNats\Services\Connection;
use LeNats\Support\Inbox;
use NatsStreamingProtocol\PubMsg;

class Publisher extends MessageStreamer
{
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var array
     */
    private $suffixes;

    /**
     * Publisher constructor.
     * @param Connection          $connection
     * @param SerializerInterface $serializer
     * @param array               $suffixes
     */
    public function __construct(Connection $connection, SerializerInterface $serializer, array $suffixes)
    {
        parent::__construct($connection);
        $this->serializer = $serializer;
        $this->suffixes = $suffixes;
    }

    /**
     * @param  CloudEvent      $event
     * @throws StreamException
     * @throws Exception
     * @return string
     */
    public function publish(CloudEvent $event): string
    {
        $subject = empty($this->suffixes) ? $event->getType() : str_replace($this->suffixes, '', $event->getType());

        $guid = $this->generator->generateString(16);

        $data = $this->serializer->serialize($event, 'json');
        $data = str_replace([
            '"propagation_stopped":false,',
            '"propagation_stopped":true,',
            '"propagation_stopped": false,',
            '"propagation_stopped": true,',
        ], '', $data); // TODO this field must be ignored

        $request = new PubMsg();
        $request->setClientID($this->connection->getConfig()->getClientID());
        $request->setGuid($guid);
        $request->setSubject($subject);
        $request->setData($data);

        $inbox = Inbox::newInbox('_STAN.acks.');
        $this->createSubscriptionInbox($inbox);

        $requestInbox = Inbox::newInbox();
        $sid = $this->createSubscriptionInbox($requestInbox);
        $this->unsubscribe($sid, 1);

        $natsSubject = $this->connection->getConfig()->getPubPrefix() . '.' . $subject;

        $this->getStream()->publish($natsSubject, $request, $requestInbox);

        return $guid;
    }
}
