<?php

namespace LeNats\Subscribers;

use LeNats\Contracts\EventDispatcherAwareInterface;
use LeNats\Events\Nats\Pong;
use LeNats\Events\React\Close;
use LeNats\Events\React\End;
use LeNats\Events\React\Error;
use LeNats\Exceptions\NatsException;
use LeNats\Services\Connection;
use LeNats\Subscription\CloseConnection;
use LeNats\Subscription\Subscriber;
use LeNats\Subscription\Subscription;
use LeNats\Support\Dispatcherable;
use LeNats\Support\Inbox;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ReactEventSubscriber implements EventSubscriberInterface, EventDispatcherAwareInterface
{
    use Dispatcherable;
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

    /**
     * @var CloseConnection
     */
    private $closeConnection;

    public function __construct(
        Connection $connection,
        Subscriber $subscriber,
        CloseConnection $closeConnection,
        ?LoggerInterface $logger = null
) {
        $this->connection = $connection;
        $this->logger = $logger;
        $this->closeConnection = $closeConnection;
        $this->subscriber = $subscriber;
    }

    /**
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents(): array
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
            $this->verboseLog('CLOSE: connection closed');
        }

        $this->verboseLog('CLOSE. ' . $event->message);

        $this->connection->stopAll();
    }

    public function onEnd(End $event): void
    {
        $this->gracefulShutdown();

        $this->verboseLog('END. ' . $event->message);
    }

    /**
     * @param  Error         $event
     * @throws NatsException
     */
    public function onError(Error $event): void
    {
        $this->connection->close();

        $this->connection->stopAll();

        $this->verboseLog('ERROR. ' . $event->error);

        throw new NatsException($event->error);
    }

    public function onPong(Pong $event): void
    {
        $this->verboseLog('PONG handled');
    }

    private function gracefulShutdown(): void
    {
        if ($this->connection->isConnected() && !$this->connection->isShutdown()) {
            $this->connection->setShutdown(true);
            $this->subscriber->unsubscribeAll();

            $subscription = new Subscription(Inbox::newInbox());
            $this->closeConnection->subscribe($subscription);

            $this->verboseLog('Shutdown. Unsubscribed and closed connection');
        }
    }

    private function verboseLog(string $message): void
    {
        if ($this->logger && getenv('APP_ENV') === 'dev') {
            $this->logger->info($message);
        }
    }
}
