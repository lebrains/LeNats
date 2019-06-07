<?php

namespace LeNats\Subscription;

use Exception;
use JMS\Serializer\SerializerInterface;
use LeNats\Events\CloudEvent;
use LeNats\Exceptions\StreamException;
use LeNats\Services\Configuration;
use LeNats\Services\Connection;
use LeNats\Support\Inbox;
use NatsStreamingProtocol\PubMsg;
use function React\Promise\all;

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
     * @param Configuration       $config
     * @param SerializerInterface $serializer
     * @param array               $suffixes
     */
    public function __construct(
        Connection $connection,
        Configuration $config,
        SerializerInterface $serializer,
        array $suffixes
    ) {
        parent::__construct($connection, $config);
        $this->serializer = $serializer;
        $this->suffixes = $suffixes;
    }

    /**
     * @param  CloudEvent      $event
     * @param  callable|null   $onSuccess
     * @throws StreamException
     * @throws Exception
     * @return void
     */
    public function publish(CloudEvent $event, ?callable $onSuccess = null): void
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

        $promises = [];

        $request = new PubMsg();
        $request->setClientID($this->config->getClientID());
        $request->setGuid($guid);
        $request->setSubject($subject);
        $request->setData($data);

        $inbox = Inbox::newInbox('_STAN.acks.');
        $promises[] = $this->send($inbox);

        $requestInbox = Inbox::newInbox();
        $promises[] = $this->send($requestInbox, function ($sid): void {
            $this->unsubscribe($sid, 1);
        });

        $natsSubject = $this->config->getPubPrefix() . '.' . $subject;

        $promises[] = $this->getConnection()->publish($natsSubject, $request, $requestInbox);

        $promise = all($promises);

        if ($onSuccess) {
            $promise->then(static function () use ($onSuccess, $guid): void {
                $onSuccess($guid);
            });
        }

        $promise->then(function () use ($guid): void {
            $this->getConnection()->stopTimer($guid);
        });

        $this->getConnection()->runTimer($guid, $this->config->getWriteTimeout());
    }
}
