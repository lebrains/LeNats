<?php

namespace LeNats\Listeners\Responses;

use LeNats\Events\Nats\MessageReceived;
use LeNats\Services\Configuration;
use LeNats\Services\Connection;
use LeNats\Support\Timer;
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

    public function handle(MessageReceived $message): void
    {
        $response = new CloseResponse();
        $response->mergeFromString($message->payload);

        $this->connection->stopTimer(Timer::DISCONNECTION);
        $this->connection->stopTimer($message->subscription->getSid());
        $this->connection->close();
    }
}
