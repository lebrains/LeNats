<?php

namespace LeNats\Subscribers;

use LeNats\Contracts\EventDispatcherAwareInterface;
use LeNats\Events\Nats\Connected;
use LeNats\Events\Nats\Connecting;
use LeNats\Events\Nats\Pong;
use LeNats\Exceptions\ConnectionException;
use LeNats\Exceptions\StreamException;
use LeNats\Services\Connection;
use LeNats\Support\Dispatcherable;
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
    public static function getSubscribedEvents(): array
    {
        return [
            Connecting::class => 'authorize',
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

        $payload = json_encode($auth);

        if (!$payload) {
            throw new ConnectionException('Authorization payload invalid');
        }

        $this->connection->write(Protocol::CONNECT, $payload)
            ->then(
                function (): void {
                    $this->connection->ping()
                        ->then(function (): void {
                            $this->dispatcher->addListener(Pong::class, [$this, 'handleFirstPong']);
                        });
                },
                static function (): void {
                    throw new ConnectionException('Not connected');
                }
            );

//        $this->connection->run();
    }

    public function handleFirstPong(): void
    {
        $this->dispatcher->removeListener(Pong::class, [$this, 'handleFirstPong']);
        $this->dispatch(new Connected());
    }
}
