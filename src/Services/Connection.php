<?php

namespace LeNats\Services;

use Google\Protobuf\Internal\Message as ProtoMessage;
use LeNats\Contracts\EventDispatcherAwareInterface;
use LeNats\Events\React\Close;
use LeNats\Events\React\Data;
use LeNats\Events\React\End;
use LeNats\Events\React\Error;
use LeNats\Exceptions\ConnectionException;
use LeNats\Exceptions\StreamException;
use LeNats\Subscription\Subscription;
use LeNats\Support\Dispatcherable;
use LeNats\Support\Protocol;
use LeNats\Support\RandomGenerator;
use Psr\Log\LoggerInterface;
use RandomLib\Generator;
use React\EventLoop\Factory as LoopFactory;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Connection implements EventDispatcherAwareInterface
{
    use Dispatcherable;

    /** @var ConnectionInterface */
    protected $stream;

    /**
     * @var Configuration
     */
    private $config;

    /** @var LoopInterface */
    private $loop;

    /** @var Generator|RandomGenerator */
    private $generator;

    /**
     * @var ContainerInterface
     */
    private $container;

    /** @var Subscription[] */
    private $subscriptions = [];

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    public function __construct(Configuration $config, ?LoggerInterface $logger = null)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @param int|null $timeout
     * @throws ConnectionException
     */
    public function open(?int $timeout = null): void
    {
        $this->connect();

        $this->wait($timeout);

        if ($this->stream === null) {
            throw new ConnectionException('Not connected');
        }
    }

    /**
     * @return \React\Promise\FulfilledPromise|\React\Promise\Promise|\React\Promise\PromiseInterface|\React\Promise\RejectedPromise|Connector|null
     * @throws ConnectionException
     */
    private function connect()
    {
        $connector = new Connector($this->getLoop(), $this->config->getContext());
        $uri = $this->config->getDsn();

        if (!($promise = $connector->connect($uri))) {
            throw new ConnectionException('Can`t connect');
        }

        $promise->then(function (ConnectionInterface $connection) {
            $this->stream = $connection;
            $this->forwardEvents();
        });

        $promise->otherwise(static function ($reason) {
            throw new ConnectionException($reason);
        });

        return $promise;
    }

    private function getLoop(): LoopInterface
    {
        if ($this->loop) {
            return $this->loop;
        }

        return $this->loop = LoopFactory::create();
    }

    private function forwardEvents(): void
    {
        $this->stream->on('data', function ($data) {
            $this->dispatch(new Data($data));
        });

        $this->stream->on('end', function () {
            $this->dispatch(new End());
        });

        $this->stream->on('error', function ($error) {
            $this->dispatch(new Error($error));
        });

        $this->stream->on('close', function () {
            $this->dispatch(new Close());
        });
    }

    public function wait(?int $timeout = null): void
    {
        if ($timeout > 0) {
            $this->getLoop()->addTimer($timeout, function () {
                $this->getLoop()->stop();
            });
        }

        $this->getLoop()->run();
    }

    public function isConnected(): bool
    {
        return $this->stream !== null && $this->stream->isReadable() && $this->stream->isWritable();
    }

    public function stopWaiting(): void
    {
        $this->getLoop()->stop();
    }

    public function getConfig(): Configuration
    {
        return $this->config;
    }

    /**
     * @return bool
     * @throws StreamException
     */
    public function ping(): bool
    {
        return $this->write(Protocol::PING);
    }

    /**
     * @param string $method
     * @param string|array|null $params
     * @param string|ProtoMessage|null $payload
     * @return bool
     * @throws StreamException
     */
    public function write(string $method, $params = null, $payload = null): bool
    {
        if (!in_array($method, Protocol::getClientMethods(), true)) {
            throw new StreamException('Method not exists: ' . $method);
        }

        $message = $method;

        if ($params) {
            if (is_array($params)) {
                $params = implode(Protocol::SPC, $params);
            }

            $message .= Protocol::SPC . $params;

            if ($payload !== null) {
                $message .= Protocol::SPC;

                $message .= strlen($payload) . Protocol::CR_LF . $payload;
            }
        }

        $message .= Protocol::CR_LF;

        if ($this->logger && getenv('APP_ENV') === 'dev') {
            $this->logger->info('<<<< ' . $message);
        }

        return $this->stream->write($message);
    }

    /**
     * @param string $subject
     * @param null $payload
     * @param null $inbox
     * @return bool
     * @throws StreamException
     */
    public function publish(string $subject, $payload = null, $inbox = null): bool
    {
        $params = [$subject];
        $payload = $payload ?? '';

        if ($inbox) {
            $params[] = $inbox;
        }

        if (is_object($payload) && $payload instanceof ProtoMessage) {
            $payload = $payload->serializeToString();
        }

        return $this->write(Protocol::PUB, $params, $payload);
    }

    public function close(): void
    {
        $this->stream->close();
    }

    public function __destruct()
    {
        $this->dispatch(new End('See you soon!'));
    }
}
