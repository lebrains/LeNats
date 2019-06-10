<?php

namespace LeNats\Subscribers;

use LeNats\Contracts\BufferInterface;
use LeNats\Contracts\EventDispatcherAwareInterface;
use LeNats\Events\Nats\BufferUpdated;
use LeNats\Events\Nats\Connecting;
use LeNats\Events\Nats\Info;
use LeNats\Events\Nats\MessageReceived;
use LeNats\Events\Nats\Ping;
use LeNats\Events\Nats\Pong;
use LeNats\Events\React\Data;
use LeNats\Events\React\Error;
use LeNats\Exceptions\NatsException;
use LeNats\Exceptions\StreamException;
use LeNats\Exceptions\SubscriptionNotFoundException;
use LeNats\Subscription\Subscriber;
use LeNats\Support\Dispatcherable;
use LeNats\Support\Protocol;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MessageProcessor implements EventSubscriberInterface, EventDispatcherAwareInterface
{
    use Dispatcherable;

    /** @var array */
    private static $commandEvents = [
        Protocol::INFO => [Info::class, Connecting::class],
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
        $this->buffer->append($event->data);

        $this->dispatch(new BufferUpdated());
    }

    public function processBuffer(): void
    {
        $buffer = $this->buffer;

        while ($line = $buffer->getLine()) {
            $handled = false;
            $commands = Protocol::getServerMethods();

            while (!$handled && $command = array_pop($commands)) {
                if (strpos($line, $command) !== 0) {
                    continue;
                }

                if (!array_key_exists($command, self::$commandEvents)) {
                    break;
                }

                $args = [$line];

                if ($command === Protocol::MSG) {
                    try {
                        if (!$message = $this->getFullMessage($line)) {
                            // Wait for next tick - buffer has not full message
                            return;
                        }

                        $line .= Protocol::CR_LF . $message[1];
                        $buffer->acknowledge($line);

                        $subscription = $this->subscriber->getSubscription($message[0]);

                        $this->dispatch(new MessageReceived($subscription, $message[1]), $message[0]);
                    } catch (SubscriptionNotFoundException $e) {
                        // ignore
                    } catch (NatsException $e) {
                        $this->dispatch(new Error($e->getMessage()));
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

                if ($this->logger && getenv('APP_ENV') === 'dev') {
                    $this->logger->info('>>>> ' . $line);
                }
            }

            if (!$handled) {
                $this->dispatch(new Error('Message not handled: ' . $line));
            }
        }
    }

    /**
     * @param  string          $rawMessage
     * @throws StreamException
     * @return array
     */
    private function getFullMessage(string $rawMessage): ?array
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
            $buffer->resetPosition();

            if (strlen($payload) !== $length) {
                return null;
            }
        }

        return [$sid, $payload, $subject, $replay];
    }
}
