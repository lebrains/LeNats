<?php

namespace LeNats\Services;

use function Clue\React\Block\await;
use LeNats\Contracts\EventDispatcherAwareInterface;
use LeNats\Events\Nats\BufferUpdated;
use LeNats\Events\Nats\NatsConfigured;
use LeNats\Events\React\Close;
use LeNats\Events\React\Data;
use LeNats\Events\React\End;
use LeNats\Events\React\Error;
use LeNats\Exceptions\ConnectionException;
use LeNats\Support\Dispatcherable;
use LeNats\Support\Stream;
use Psr\Log\LoggerInterface;
use React\EventLoop\Factory as LoopFactory;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Promise\Deferred;

class Connection implements EventDispatcherAwareInterface
{
    use Dispatcherable;

    /** @var Stream|null */
    protected $stream;

    /** @var array */
    private static $forwardedEvents = [
        Data::class  => 'data',
        End::class   => 'end',
        Error::class => 'error',
        Close::class => 'close',
    ];

    /**
     * @var Configuration
     */
    private $config;

    /** @var LoopInterface */
    private $loop;

    /** @var TimerInterface[] */
    private $timers = [];

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    /** @var bool */
    private $shutdown = false;

    /** @var bool */
    private $running = false;

    public function __construct(Configuration $config, ?LoggerInterface $logger = null)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function __destruct()
    {
        if ($this->stream !== null) {
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

    public function runTimer(string $timerName, int $timeout): void
    {
        $this->timers[$timerName] = $this->getLoop()->addTimer($timeout, function () use ($timerName): void {
            $this->stopTimer($timerName);
        });

        $this->run();
    }

    public function run(): void
    {
        if ($this->running) {
            return;
        }

        $this->running = true;
        $this->getLoop()->run();
    }

    /**
     * @throws ConnectionException
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->stream !== null && $this->getStream()->isConnected();
    }

    public function stopTimer(string $timerName): void
    {
        if (array_key_exists($timerName, $this->timers)) {
            $this->getLoop()->cancelTimer($this->timers[$timerName]);

            unset($this->timers[$timerName]);
        }

        if (empty($this->timers) && $this->running) {
            $this->stop();
        }
    }

    public function stop(): void
    {
        $this->getLoop()->stop();
        $this->running = false;
    }

    public function stopAll(): void
    {
        while (!empty($this->timers)) {
            $this->getLoop()->cancelTimer(array_pop($this->timers));
        }

        $this->stop();
    }

    public function getConfig(): Configuration
    {
        return $this->config;
    }

    public function close(): void
    {
        $this->stream = null;
    }

    public function getLoop(): LoopInterface
    {
        if ($this->loop !== null) {
            return $this->loop;
        }

        return $this->loop = LoopFactory::create();
    }

    public function setLoop(LoopInterface $loop): void
    {
        $this->loop = $loop;
    }

    /**
     * @throws ConnectionException
     * @throws \Exception
     * @return Stream
     */
    public function getStream(): Stream
    {
        if ($this->stream === null) {
            $this->setShutdown(false);

            $stream = Stream::connect($this->getLoop(), $this->getConfig());

            $this->stream = $stream;
            $this->configureStream($stream);

            $deferred = new Deferred();

            $this->dispatcher->addListener(NatsConfigured::class, static function () use ($deferred): void {
                $deferred->resolve();
            });

            await($deferred->promise(), $this->getLoop(), $this->config->getConnectionTimeout());
        }

        return $this->stream;
    }

    public function setStream(Stream $stream): void
    {
        $this->stream = $stream;
    }

    public function configureStream(
        Stream $stream,
        array $forwardEvents = [Data::class, End::class, Error::class, Close::class]
    ): void {
        if ($this->getConfig()->isDebug()) {
            $stream->setLogger($this->logger);
        }

        foreach ($forwardEvents as $eventClass) {
            $event = static::$forwardedEvents[$eventClass] ?? null;

            if ($event !== null) {
                $stream->on($event, function ($data = null) use ($eventClass): void {
                    $this->dispatch(new $eventClass($data));
                });
            }
        }
    }

    public function processBufferOnNextTick(): void
    {
        $this->getLoop()->futureTick(function () {
            $this->dispatch(new BufferUpdated());
        });
    }
}
