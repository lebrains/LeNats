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
use React\Promise\PromiseInterface;

abstract class MessageStreamer
{
    /** @var Generator|RandomGenerator */
    protected $generator;

    /**
     * @var Configuration
     */
    protected $config;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection, Configuration $config)
    {
        $this->connection = $connection;
        $this->config = $config;

        if (PHP_VERSION_ID > 70000) {
            $this->generator = new RandomGenerator();
        } else {
            $randomFactory = new Factory();
            $this->generator = $randomFactory->getLowStrengthGenerator();
        }
    }

    /**
     * @param  string                   $sid
     * @param  int|null                 $quantity
     * @throws StreamException
     * @return PromiseInterface|Promise
     */
    public function unsubscribe(string $sid, ?int $quantity = null)
    {
        $params = [$sid];

        if ($quantity) {
            $params[] = $quantity;
        }

        return $this->getConnection()->write(Protocol::UNSUB, $params);
    }

    public function run(int $timeout = 0): void
    {
        $this->getConnection()->run($timeout);
    }

    public function stop(): void
    {
        $this->getConnection()->stop();
    }

    protected function getConnection(): Connection
    {
        if (!$this->connection->isConnected()) {
            $this->connection->open(30); // TODO
        }

        return $this->connection;
    }

    /**
     * @param  string                   $subject
     * @param  string|callable|null     $sid
     * @param  callable|null            $onSuccess
     * @throws StreamException
     * @throws Exception
     * @return PromiseInterface|Promise
     */
    protected function send(string $subject, $sid = null, ?callable $onSuccess = null)
    {
        if ($sid instanceof Closure) {
            [$onSuccess, $sid] = [$sid, null];
        }

        if ($sid === null) {
            $sid = $this->generator->generateString(16);
        }

        $promise = $this->getConnection()->write(Protocol::SUB, [$subject, $sid]);

        if ($onSuccess) {
            $promise->then(static function () use ($sid, $onSuccess): void {
                $onSuccess($sid);
            });
        }

        return $promise;
    }
}
