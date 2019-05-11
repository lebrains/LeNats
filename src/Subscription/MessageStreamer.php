<?php

namespace LeNats\Subscription;

use LeNats\Services\Configuration;
use LeNats\Services\Connection;
use LeNats\Support\Protocol;
use LeNats\Support\RandomGenerator;
use RandomLib\Factory;
use RandomLib\Generator;

abstract class MessageStreamer
{
    /** @var Generator|RandomGenerator */
    protected $generator;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Configuration
     */
    protected $config;

    public function __construct(
        Connection $connection,
        Configuration $config
    ) {
        $this->connection = $connection;
        $this->config = $config;

        if (PHP_VERSION_ID > 70000 === true) {
            $this->generator = new RandomGenerator();
        } else {
            $randomFactory = new Factory();
            $this->generator = $randomFactory->getLowStrengthGenerator();
        }
    }

    protected function getConnection(): Connection
    {
        if (!$this->connection->isConnected()) {
            $this->connection->open(30); // TODO
        }

        return $this->connection;
    }

    public function unsubscribe(string $sid, ?int $quantity = null): bool
    {
        $params = [$sid];

        if ($quantity) {
            $params[] = $quantity;
        }

        return $this->getConnection()->write(Protocol::UNSUB, $params);
    }

    protected function send(string $subject, ?string $sid = null): ?string
    {
        $sid = $sid ?? $this->generator->generateString(16);

        $isSuccess = $this->getConnection()->write(Protocol::SUB, [
            $subject,
            $sid,
        ]);

        if (!$isSuccess) {
            return null;
        }

        return $sid;
    }
}
