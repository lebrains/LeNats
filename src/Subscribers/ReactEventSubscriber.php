<?php

namespace LeNats\Subscribers;

use LeNats\Events\Nats\Pong;
use LeNats\Events\React\Close;
use LeNats\Events\React\End;
use LeNats\Events\React\Error;
use LeNats\Exceptions\NatsException;
use LeNats\Services\Connection;
use LeNats\Subscription\Subscriber;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ReactEventSubscriber implements EventSubscriberInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    /**
     * @var Subscriber
     */
    private $subscriber;

    public function __construct(Connection $connection, Subscriber $subscriber, ?LoggerInterface $logger = null)
    {
        $this->connection = $connection;
        $this->logger = $logger;
        $this->subscriber = $subscriber;
    }

    /**
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return [
            Error::class => 'onError',
            Close::class => 'onClose',
            End::class   => 'onEnd',
            Pong::class  => 'onPong',
        ];
    }

    public function onClose(Close $event): void
    {
        if ($this->connection->isConnected()) {
            $this->connection->close();

            $this->verboseLog('CLOSE: connection closed');
        }

        $this->verboseLog('CLOSE. ' . $event->message);

        $this->connection->stop();
    }

    private function gracefulShutdown(): void
    {
        if ($this->connection->isConnected()) {
            $this->subscriber->unsubscribeAll();

            $this->connection->close();
            $this->verboseLog('Shutdown. Unsubscribed and closed connection');
        }

        $this->connection->stop();
    }

    private function verboseLog(string $message): void
    {
        if ($this->logger && getenv('APP_ENV') === 'dev') {
            $this->logger->info($message);
        }
    }

    public function onEnd(End $event): void
    {
        $this->gracefulShutdown();

        $this->verboseLog('END. ' . $event->message);
    }

    /**
     * @param Error $event
     * @throws NatsException
     */
    public function onError(Error $event): void
    {
        $this->gracefulShutdown();

        $this->verboseLog('ERROR. ' . $event->error);

        throw new NatsException($event->error);
    }

    public function onPong(Pong $event): void
    {
        $this->verboseLog('PONG handled');
    }
}
