<?php

namespace LeNats\Listeners\Responses;

use LeNats\Contracts\EventDispatcherAwareInterface;
use LeNats\Events\Nats\NatsConfigured;
use LeNats\Events\Nats\SubscriptionMessageReceived;
use LeNats\Services\Configuration;
use LeNats\Services\Connection;
use LeNats\Support\Dispatcherable;
use NatsStreamingProtocol\ConnectResponse;

class ConnectionResponseListener implements EventDispatcherAwareInterface
{
    use Dispatcherable;
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
        $response = new ConnectResponse();
        $response->mergeFromString($message->payload);

        $this->config->configureConnection($response);

        $this->connection->stopTimer($message->subscription->getSid());
        $this->dispatch(new NatsConfigured());
    }
}
