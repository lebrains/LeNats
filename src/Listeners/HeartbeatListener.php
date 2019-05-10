<?php

namespace LeNats\Listeners;

use LeNats\Events\Nats\MessageReceived;
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

    public function handle(MessageReceived $event): void
    {
        $this->connection->publish($event->subscription->getInbox());
    }
}
