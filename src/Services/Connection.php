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
use React\EventLoop\TimerInterface;
use React\Promise\Deferred;
use React\Promise\FulfilledPromise;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use React\Promise\RejectedPromise;
use function React\Promise\Timer\timeout;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;

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

    /** @var TimerInterface[] */
    private $timers = [];

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    /** @var bool */
    private $shutdown = false;

    public function __construct(Configuration $config, ?LoggerInterface $logger = null)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function __destruct()
    {
        if ($this->isConnected()) {
            $this->dispatch(new End('See you soon!'));
        }
    }

    public function isShutdown(): bool
    {
        return $this->shutdown;
    }

    public function setShutdown(bool $shutdown): void
    {
        $this->shutdown = $shutdown;
    }

    /**
     * @param  int|null            $timeout
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

    public function run(int $timeout = 0): void
    {
        if ($timeout > 0) {
            $this->timers[] = $this->getLoop()->addTimer($timeout, function (): void {
                $this->getLoop()->stop();
            });
        }

        $this->getLoop()->run();
    }

    public function isConnected(): bool
    {
        return $this->stream !== null && $this->stream->isReadable() && $this->stream->isWritable();
    }

    public function stop(bool $all = false): void
    {
        if (!empty($this->timers)) {
            $this->getLoop()->cancelTimer(array_pop($this->timers));

            if ($all) {
                while ($timer = array_pop($this->timers)) {
                    $this->getLoop()->cancelTimer(array_pop($this->timers));
                }
            }
        }

        $this->getLoop()->stop();
    }

    public function getConfig(): Configuration
    {
        return $this->config;
    }

    /**
     * @throws StreamException
     * @return PromiseInterface|Promise
     */
    public function ping()
    {
        return $this->write(Protocol::PING);
    }

    /**
     * @param  string                   $method
     * @param  string|array|null        $params
     * @param  string|null              $payload
     * @throws StreamException
     * @return PromiseInterface|Promise
     */
    public function write(string $method, $params = null, ?string $payload = null)
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

        $this->getLoop()->futureTick(function () use ($message, $deferred): void {
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
     * @param  string                   $subject
     * @param  string|ProtoMessage|null $payload
     * @param  string|null              $inbox
     * @throws StreamException
     * @return PromiseInterface|Promise
     */
    public function publish(string $subject, $payload = null, ?string $inbox = null)
    {
        $params = [$subject];

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

    /**
     * @param  int|null                                                            $timeout
     * @throws ConnectionException
     * @return FulfilledPromise|Promise|PromiseInterface|RejectedPromise|Connector
     */
    private function connect(?int $timeout = null)
    {
        $this->setShutdown(false);

        $connector = new Connector($this->getLoop(), $this->config->getContext());
        $uri = $this->config->getDsn();

        $connectionPromise = $connector->connect($uri);

        if ($connectionPromise === null) {
            throw new ConnectionException('Can`t connect');
        }

        $connectionPromise->then(
            function (ConnectionInterface $connection): void {
                $this->stream = $connection;
                $this->forwardEvents();
            },
            static function ($reason): void {
                throw new ConnectionException($reason);
            }
        );

        if ($timeout) {
            $timeoutPromise = timeout($connectionPromise, $timeout, $this->getLoop());
        }

        return $timeoutPromise ?? $connectionPromise;
    }

    private function getLoop(): LoopInterface
    {
        if ($this->loop !== null) {
            return $this->loop;
        }

        return $this->loop = LoopFactory::create();
    }

    private function forwardEvents(): void
    {
        $this->stream->on('data', function ($data): void {
            $this->dispatch(new Data($data));
        });

        $this->stream->on('end', function (): void {
            $this->dispatch(new End());
        });

        $this->stream->on('error', function ($error): void {
            $this->dispatch(new Error($error));
        });

        $this->stream->on('close', function (): void {
            $this->dispatch(new Close());
        });
    }
}
