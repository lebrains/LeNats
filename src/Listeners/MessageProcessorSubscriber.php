<?php

namespace LeNats\Listeners;

use LeNats\Contracts\BufferInterface;
use LeNats\Contracts\EventDispatcherAwareInterface;
use LeNats\Events\Nats\BufferUpdated;
use LeNats\Events\Nats\Info;
use LeNats\Events\Nats\NatsConnected;
use LeNats\Events\Nats\Ping;
use LeNats\Events\Nats\Pong;
use LeNats\Events\Nats\UndefinedMessageReceived;
use LeNats\Events\React\Data;
use LeNats\Events\React\Error;
use LeNats\Exceptions\StreamException;
use LeNats\Exceptions\SubscriptionNotFoundException;
use LeNats\Subscription\Subscriber;
use LeNats\Subscription\Subscription;
use LeNats\Support\Dispatcherable;
use LeNats\Support\Protocol;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MessageProcessorSubscriber implements EventSubscriberInterface, EventDispatcherAwareInterface
{
    use Dispatcherable;

    private const MAX_MESSAGES_IN_ONE_TICK = 10;

    /** @var array */
    private static $commandEvents = [
        Protocol::INFO => [Info::class, NatsConnected::class],
        Protocol::MSG  => [],
        Protocol::PING => [Ping::class],
        Protocol::PONG => [Pong::class],
        Protocol::ERR  => [Error::class],
        Protocol::OK   => [],
    ];

    /**
     * @var BufferInterface
     */
    private $buffer;

    /**
     * @var Subscriber
     */
    private $subscriber;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    public function __construct(BufferInterface $buffer, Subscriber $subscriber, ?LoggerInterface $logger = null)
    {
        $this->buffer = $buffer;
        $this->subscriber = $subscriber;
        $this->logger = $logger;
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * ['eventName' => 'methodName']
     *  * ['eventName' => ['methodName', $priority]]
     *  * ['eventName' => [['methodName1', $priority], ['methodName2']]]
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents(): array
    {
        return [
            Data::class                => 'bufferize',
            BufferUpdated::class       => 'processBuffer',
        ];
    }

    public function bufferize(Data $event): void
    {
        $this->buffer->append($event->message);

        $this->dispatch(new BufferUpdated());
    }

    /**
     * @throws StreamException
     */
    public function processBuffer(): void
    {
        $buffer = $this->buffer;
        $processed = 0;

        while (($line = $buffer->getLine()) && (self::MAX_MESSAGES_IN_ONE_TICK > $processed)) {
            $handled = false;
            $commands = Protocol::getServerMethods();

            while (!$handled && $command = array_shift($commands)) {
                if (strpos($line, $command) !== 0) {
                    continue;
                }

                $args = [$line];

                if ($command === Protocol::MSG) {
                    if (!$message = $this->getFullMessage($line)) {
                        // Wait for next tick - buffer has not full message
                        return;
                    }

                    $line .= Protocol::CR_LF . $message->payload;
                    $buffer->acknowledge($line);

                    try {
                        $subscription = $this->getSubscription($message->sid);

                        $this->dispatch($message->toSubscribtion($subscription), $message->sid);
                    } catch (SubscriptionNotFoundException $e) {
                        $this->dispatch($message);
                    }
                } else {
                    $buffer->acknowledge($line);
                    foreach (self::$commandEvents[$command] as $class) {
                        $event = $class;

                        if (class_exists($class)) {
                            $event = new $class(...$args);
                        }

                        $this->dispatch($event);
                    }
                }

                $handled = true;
                ++$processed;

                if ($this->logger && $this->subscriber->getConnection()->getConfig()->isDebug()) {
                    $this->logger->info(sprintf('>>>> %s ...', substr($line, 0, 80)));
                }
            }

            if (!$handled) {
                throw new StreamException('Message not handled: ' . $line);
            }
        }
    }

    /**
     * @param  string                        $sid
     * @throws SubscriptionNotFoundException
     * @return Subscription
     */
    protected function getSubscription(string $sid): Subscription
    {
        return $this->subscriber->getSubscription($sid);
    }

    /**
     * @param  string                        $rawMessage
     * @throws StreamException
     * @return UndefinedMessageReceived|null
     */
    private function getFullMessage(string $rawMessage): ?UndefinedMessageReceived
    {
        $buffer = $this->buffer;
        $message = explode(Protocol::SPC, $rawMessage, 5);
        array_shift($message);

        if (count($message) < 3) {
            throw new StreamException('Wrong message format: ' . $rawMessage);
        }
        $length = (int)array_pop($message);
        $message = array_pad($message, 3, null);
        [$subject, $sid, $replay] = $message;
        $payload = '';

        if ($length > 0) {
            $payload = $buffer->get($length, strlen($rawMessage) + strlen(Protocol::CR_LF));

            if ($payload === null) {
                return $payload;
            }
        }

        return new UndefinedMessageReceived($sid, $payload, $subject, $replay);
    }
}
