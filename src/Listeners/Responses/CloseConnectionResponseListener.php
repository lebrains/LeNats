<?php

namespace LeNats\Listeners\Responses;

use LeNats\Events\Nats\SubscriptionMessageReceived;
use LeNats\Services\Configuration;
use LeNats\Services\Connection;
use NatsStreamingProtocol\CloseResponse;

class CloseConnectionResponseListener
{
    /**
     * @var Configuration
     */
    private $config;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Configuration $config, Connection $connection)
    {
        $this->config = $config;
        $this->connection = $connection;
    }

    public function handle(SubscriptionMessageReceived $message): void
    {
        $response = new CloseResponse();
        $response->mergeFromString($message->payload);

        $this->connection->stopTimer($message->subscription->getSid());
    }
}
