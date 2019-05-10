<?php

namespace LeNats\Subscription;

use LeNats\Events\CloudEvent;
use LeNats\Services\Configuration;
use LeNats\Services\Connection;
use LeNats\Services\Serializer;
use LeNats\Support\Inbox;
use NatsStreamingProtocol\PubMsg;

class Publisher extends MessageStreamer
{
    /**
     * @var Serializer
     */
    private $serializer;

    public function __construct(
        Connection $connection,
        Configuration $config,
        Serializer $serializer
    ) {
        parent::__construct($connection, $config);
        $this->serializer = $serializer;
    }

    public function publish(CloudEvent $event): ?string
    {
        $subject = str_replace(['.created', '.updated', '.deleted'], '', $event->getType());

        $guid = $this->generator->generateString(16);

        $data = $this->serializer->serialize($event, 'json');
        $data = str_replace([
            '"propagation_stopped":false,',
            '"propagation_stopped":true,',
        ], '', $data);

        $request = new PubMsg();
        $request->setClientID($this->config->getClientID());
        $request->setGuid($guid);
        $request->setSubject($subject);
        $request->setData($data);

        $inbox = Inbox::newInbox('_STAN.acks.');

        if (!($sid = $this->send($inbox))) {
            return null;
        }

        $requestInbox = Inbox::newInbox();

        if (!($sid = $this->send($requestInbox))) {
            return null;
        }

        $this->unsubscribe($sid, 1);

        $natsSubject = $this->config->getPubPrefix() . '.' . $subject;

        $this->getConnection()->publish(
            $natsSubject,
            $request,
            $requestInbox
        );

        return $guid;
    }
}
