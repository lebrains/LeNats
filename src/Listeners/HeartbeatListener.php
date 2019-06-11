<?php

namespace LeNats\Listeners;

use LeNats\Events\Nats\SubscriptionMessageReceived;
use LeNats\Exceptions\ConnectionException;
use LeNats\Exceptions\StreamException;
use LeNats\Services\Connection;

class HeartbeatListener
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param  SubscriptionMessageReceived $event
     * @throws StreamException
     * @throws ConnectionException
     */
    public function handle(SubscriptionMessageReceived $event): void
    {
        $this->connection->getStream()->publish($event->subscription->getInbox());
    }
}
