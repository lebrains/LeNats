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
use LeNats\Support\Dispatcherable;
use LeNats\Support\Protocol;
use Psr\Log\LoggerInterface;
use React\EventLoop\Factory as LoopFactory;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\FulfilledPromise;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use React\Promise\RejectedPromise;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;
use function React\Promise\Timer\timeout;

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

    /** @var PromiseInterface */
    private $promise;

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
        $this->connect($timeout);

        $this->run();

        if ($this->stream === null) {
            throw new ConnectionException('Not connected');
        }
    }

    public function run(?int $timeout = null): void
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

    public function stop(): void
    {
        $this->getLoop()->stop();
    }

    public function getConfig(): Configuration
    {
        return $this->config;
    }

    /**
     * @return Promise
     * @throws StreamException
     */
    public function ping(): Promise
    {
        return $this->write(Protocol::PING);
    }

    /**
     * @param string $method
     * @param string|array|null $params
     * @param string|ProtoMessage|null $payload
     * @return Promise
     * @throws StreamException
     */
    public function write(string $method, $params = null, $payload = null): Promise
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

        $deferred = new Deferred();

        $this->getLoop()->futureTick(function () use ($message, $deferred) {
            $result = $this->stream->write($message);

            if ($result) {
                $deferred->resolve();
            } else {
                $deferred->reject(new ConnectionException('Write message error'));
            }
        });

        return $deferred->promise();
    }

    /**
     * @param string $subject
     * @param null $payload
     * @param null $inbox
     * @return Promise
     * @throws StreamException
     */
    public function publish(string $subject, $payload = null, $inbox = null): Promise
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
        if ($this->isConnected()) {
            $this->dispatch(new End('See you soon!'));
        }
    }

    /**
     * @param int|null $timeout
     * @return FulfilledPromise|Promise|PromiseInterface|RejectedPromise|Connector
     * @throws ConnectionException
     */
    private function connect(?int $timeout = null)
    {
        $connector = new Connector($this->getLoop(), $this->config->getContext());
        $uri = $this->config->getDsn();

        if (!($connectionPromise = $connector->connect($uri))) {
            throw new ConnectionException('Can`t connect');
        }

        $connectionPromise->then(function (ConnectionInterface $connection) {
            $this->stream = $connection;
            $this->forwardEvents();
        });

        $connectionPromise->otherwise(static function ($reason) {
            throw new ConnectionException($reason);
        });

        if ($timeout) {
            $timeoutPromise = timeout($connectionPromise, $timeout, $this->getLoop());
        }

        return $timeoutPromise ?? $connectionPromise;
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
}
