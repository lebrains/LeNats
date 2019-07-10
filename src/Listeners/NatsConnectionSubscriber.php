<?php

namespace LeNats\Listeners;

use LeNats\Contracts\EventDispatcherAwareInterface;
use LeNats\Events\Nats\Info;
use LeNats\Events\Nats\NatsConnected;
use LeNats\Events\Nats\NatsStreamingConnected;
use LeNats\Events\Nats\Pong;
use LeNats\Exceptions\ConnectionException;
use LeNats\Exceptions\StreamException;
use LeNats\Services\Connection;
use LeNats\Support\Dispatcherable;
use LeNats\Support\Protocol;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NatsConnectionSubscriber implements EventSubscriberInterface, EventDispatcherAwareInterface
{
    use Dispatcherable;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents(): array
    {
        return [
            Info::class => 'updateConfiguration',
            NatsConnected::class => 'handle',
        ];
    }

    /**
     * @throws ConnectionException
     * @throws StreamException
     */
    public function handle(): void
    {
        $config = $this->connection->getConfig();

        $auth = [
            'lang'     => $config->getLang(),
            'version'  => $config->getVersion(),
            'verbose'  => $config->isVerbose(),
            'pedantic' => $config->isPedantic(),
            'protocol' => $config->getProtocol(),
            'user'     => $config->getUser(),
            'pass'     => $config->getPass(),
        ];

        $payload = json_encode($auth);

        if (!$payload) {
            throw new ConnectionException('Authorization payload invalid');
        }

        $this->dispatcher->addListener(Pong::class, [$this, 'handleFirstPong']);
        $this->connection->getStream()->write(Protocol::CONNECT, $payload);
        $this->connection->getStream()->ping();
    }

    public function handleFirstPong(): void
    {
        $this->dispatcher->removeListener(Pong::class, [$this, 'handleFirstPong']);
        $this->dispatch(new NatsStreamingConnected());
    }

    public function updateConfiguration(Info $event)
    {
        $maxPayload = $event->message['max_payload'] ?? 0;

        if ($maxPayload > 0) {
            $this->connection->getConfig()->setMaxPayload($maxPayload);
        }
    }
}
