<?php

namespace LeNats\Listeners\Responses;

use LeNats\Events\Nats\MessageReceived;
use LeNats\Services\Configuration;
use LeNats\Services\Connection;
use NatsStreamingProtocol\ConnectResponse;

class ConnectionResponseListener
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
        $response = new ConnectResponse();
        $response->mergeFromString($message->payload);

        $this->config->configureConnection($response);

        $this->connection->stop();
    }
}
