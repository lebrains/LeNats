<?php

namespace LeNats\Subscription;

use Exception;
use LeNats\Events\CloudEvent;
use LeNats\Exceptions\StreamException;
use LeNats\Services\Configuration;
use LeNats\Services\Connection;
use LeNats\Services\Serializer;
use LeNats\Support\Inbox;
use NatsStreamingProtocol\PubMsg;
use function React\Promise\all;

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

    /**
     * @param CloudEvent $event
     * @param callable|null $onSuccess
     * @return void
     * @throws StreamException
     * @throws Exception
     */
    public function publish(CloudEvent $event, ?callable $onSuccess = null): void
    {
        $subject = str_replace(['.created', '.updated', '.deleted'], '', $event->getType());

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
        $promises[] = $this->send($requestInbox, function ($sid) {
            $this->unsubscribe($sid, 1);
        });

        $natsSubject = $this->config->getPubPrefix() . '.' . $subject;

        $promises[] = $this->getConnection()->publish(
            $natsSubject,
            $request,
            $requestInbox
        );

        $promise = all($promises);

        if ($onSuccess) {
            $promise->then(static function () use ($onSuccess, $guid) {
                $onSuccess($guid);
            });
        }

        $promise->then(function () {
            $this->stop();
        });

        $this->run();
    }
}
