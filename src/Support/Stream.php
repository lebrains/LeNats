<?php

namespace LeNats\Support;

use function Clue\React\Block\await;
use Google\Protobuf\Internal\Message as ProtoMessage;
use LeNats\Exceptions\ConnectionException;
use LeNats\Exceptions\StreamException;
use LeNats\Services\Configuration;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Socket\ConnectionInterface;
use React\Socket\Connector;

class Stream
{
    /** @var ConnectionInterface */
    protected $stream;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * @var int
     */
    private $writeTimeout;

    /**
     * @var Configuration
     */
    private $config;

    public function __construct(ConnectionInterface $stream, LoopInterface $loop, Configuration $config)
    {
        $this->stream = $stream;
        $this->loop = $loop;
        $this->config = $config;
    }

    public function __destruct()
    {
        if ($this->isConnected()) {
            $this->stream->close();
        }
    }

    public function setLogger(?LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @param  LoopInterface       $loop
     * @param  Configuration       $config
     * @throws ConnectionException
     * @return Stream
     */
    public static function connect(LoopInterface $loop, Configuration $config): Stream
    {
        $connector = new Connector($loop, $config->getContext());
        $uri = $config->getDsn();

        $connectionPromise = $connector->connect($uri);

        if ($connectionPromise === null) {
            throw new ConnectionException('Can`t connect');
        }

        $stream = await($connectionPromise, $loop, $config->getConnectionTimeout());

        $instance = new static($stream, $loop, $config);

        $instance->setWriteTimeout($config->getWriteTimeout());

        return $instance;
    }

    /**
     * @param  string              $method
     * @param  string|array|null   $params
     * @param  string|null         $payload
     * @throws ConnectionException
     * @throws StreamException
     * @return bool
     */
    public function write(string $method, $params = null, ?string $payload = null): bool
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

        $this->log($message);

        $deferred = new Deferred();

        $this->loop->futureTick(function () use ($message, $deferred): void {
            $chunks = str_split($message, $this->config->getMaxPayload());
            $result = true;

            foreach ($chunks as $chunk) {
                $result &= $this->stream->write($chunk);
            }

            if ($result) {
                $deferred->resolve($result);
            } else {
                $deferred->reject(new ConnectionException('Write message error'));
            }
        });

        try {
            return await($deferred->promise(), $this->loop, $this->writeTimeout);
        } catch (\Throwable $e) {
            throw new ConnectionException($e->getMessage());
        }
    }

    /**
     * @param  string                   $subject
     * @param  string|ProtoMessage|null $payload
     * @param  string|null              $inbox
     * @throws ConnectionException
     * @throws StreamException
     * @return bool
     */
    public function publish(string $subject, $payload = null, ?string $inbox = null): bool
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

    public function setWriteTimeout(int $writeTimeout): void
    {
        $this->writeTimeout = $writeTimeout;
    }

    /**
     * @throws StreamException
     * @throws ConnectionException
     */
    public function ping(): void
    {
        $this->write(Protocol::PING);
    }

    public function isConnected(): bool
    {
        return $this->stream !== null && $this->stream->isReadable() && $this->stream->isWritable();
    }

    public function on(string $event, callable $callback): void
    {
        $this->stream->on($event, $callback);
    }

    public function emit(string $event, array $arguments = []): void
    {
        $this->stream->emit($event, $arguments);
    }

    private function log(string $message): void
    {
        if ($this->logger === null) {
            return;
        }

        $this->logger->info('<<<< ' . $message);
    }
}
