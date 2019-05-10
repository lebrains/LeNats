<?php

namespace LeNats\Subscribers;

use LeNats\Contracts\EventDispatcherAwareInterface;
use LeNats\Events\Nats\Pong;
use LeNats\Exceptions\ConnectionException;
use LeNats\Exceptions\StreamException;
use LeNats\Services\Connection;
use LeNats\Support\Dispatcherable;
use LeNats\Support\NatsEvents;
use LeNats\Support\Protocol;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class Authorization implements EventSubscriberInterface, EventDispatcherAwareInterface
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
    public static function getSubscribedEvents()
    {
        return [
            NatsEvents::CONNECTING => 'authorize',
        ];
    }

    /**
     * @throws ConnectionException
     * @throws StreamException
     */
    public function authorize(): void
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

        if (!$this->connection->write(Protocol::CONNECT, json_encode($auth))) {
            throw new ConnectionException('Not connected');
        }

        $this->dispatcher->addListener(Pong::class, [$this, 'handleFirstPong']);
        $this->connection->ping();
    }

    public function handleFirstPong(): void
    {
        $this->dispatcher->removeListener(Pong::class, [$this, 'handleFirstPong']);
        $this->dispatch(NatsEvents::CONNECTED);
        $this->connection->stopWaiting();
    }
}
