<?php

namespace LeNats\Subscription;

use Closure;
use Exception;
use LeNats\Exceptions\StreamException;
use LeNats\Services\Configuration;
use LeNats\Services\Connection;
use LeNats\Support\Protocol;
use LeNats\Support\RandomGenerator;
use RandomLib\Factory;
use RandomLib\Generator;
use React\Promise\Promise;

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

    /**
     * @param string $sid
     * @param int|null $quantity
     * @return Promise
     * @throws StreamException
     */
    public function unsubscribe(string $sid, ?int $quantity = null): Promise
    {
        $params = [$sid];

        if ($quantity) {
            $params[] = $quantity;
        }

        return $this->getConnection()->write(Protocol::UNSUB, $params);
    }

    /**
     * @param string $subject
     * @param string|callable|null $sid
     * @param callable|null $onSuccess
     * @return Promise
     * @throws StreamException
     * @throws Exception
     */
    protected function send(string $subject, $sid = null, ?callable $onSuccess = null): Promise
    {
        if ($sid instanceof Closure) {
            [$onSuccess, $sid] = [$sid, null];
        }

        if ($sid === null) {
            $sid = $this->generator->generateString(16);
        }

        $promise = $this->getConnection()->write(Protocol::SUB, [
            $subject,
            $sid,
        ]);

        if ($onSuccess) {
            $promise->then(static function () use ($sid, $onSuccess) {
                $onSuccess($sid);
            });
        }

        return $promise;
    }

    public function run(?int $timeout = null): void
    {
        $this->getConnection()->run($timeout);
    }

    public function stop(): void
    {
        $this->getConnection()->stop();
    }
}
