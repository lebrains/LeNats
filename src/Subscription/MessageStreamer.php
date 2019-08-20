<?php

namespace LeNats\Subscription;

use Exception;
use LeNats\Exceptions\ConnectionException;
use LeNats\Exceptions\StreamException;
use LeNats\Services\Connection;
use LeNats\Support\Protocol;
use LeNats\Support\RandomGenerator;
use LeNats\Support\Stream;
use RandomLib\Factory;
use RandomLib\Generator;

abstract class MessageStreamer
{
    /** @var Generator|RandomGenerator */
    protected $generator;

    /**
     * @var Connection
     */
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;

        if (PHP_VERSION_ID > 70000) {
            $this->generator = new RandomGenerator();
        } else {
            $randomFactory = new Factory();
            $this->generator = $randomFactory->getLowStrengthGenerator();
        }
    }

    /**
     * @param  string              $sid
     * @param  int|null            $quantity
     * @throws StreamException
     * @throws ConnectionException
     */
    public function unsubscribe(string $sid, ?int $quantity = null): void
    {
        $params = [$sid];

        if ($quantity !== null) {
            $params[] = $quantity;
        }

        $this->getStream()->write(Protocol::UNSUB, $params);
    }

    /**
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * @throws ConnectionException
     * @return Stream
     */
    protected function getStream(): Stream
    {
        return $this->connection->getStream();
    }

    /**
     * @param  string          $inbox
     * @param  string          $sid
     * @throws Exception
     * @throws StreamException
     * @return string
     */
    protected function createSubscriptionInbox(string $inbox, ?string $sid = null): string
    {
        if ($sid === null) {
            $sid = $this->generator->generateString(16);
        }

        $this->getStream()->write(Protocol::SUB, [$inbox, $sid]);

        return $sid;
    }
}
