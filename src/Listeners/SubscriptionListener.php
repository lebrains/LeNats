<?php

namespace LeNats\Listeners;

use JMS\Serializer\SerializerInterface;
use LeNats\Contracts\EventDispatcherAwareInterface;
use LeNats\Events\CloudEvent;
use LeNats\Events\Nats\SubscriptionMessageReceived;
use LeNats\Exceptions\ConnectionException;
use LeNats\Exceptions\StreamException;
use LeNats\Exceptions\SubscriptionException;
use LeNats\Services\EventTypeResolver;
use LeNats\Subscription\Subscriber;
use LeNats\Support\Dispatcherable;
use NatsStreamingProtocol\MsgProto;

class SubscriptionListener implements EventDispatcherAwareInterface
{
    use Dispatcherable;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var EventTypeResolver
     */
    private $typeResolver;

    /**
     * @var Subscriber
     */
    private $subscriber;

    public function __construct(
        SerializerInterface $serializer,
        EventTypeResolver $typeResolver,
        Subscriber $subscriber
    ) {
        $this->serializer = $serializer;
        $this->typeResolver = $typeResolver;
        $this->subscriber = $subscriber;
    }

    /**
     * @param  SubscriptionMessageReceived $event
     * @throws SubscriptionException
     * @throws StreamException
     * @throws ConnectionException
     */
    public function handle(SubscriptionMessageReceived $event): void
    {
        $message = new MsgProto();
        try {
            $message->mergeFromString($event->payload);
        } catch (\Throwable $e) {
            throw new SubscriptionException($e->getMessage());
        }

        $data = json_decode($message->getData(), true);

        if (!array_key_exists('type', $data)) {
            throw new SubscriptionException('Event type not found');
        }

        $eventType = $data['type'];
        unset($data);

        $eventClass = $this->typeResolver->getClass($eventType) ?? CloudEvent::class;

        $cloudEvent = $this->serializer->deserialize($message->getData(), $eventClass, 'json');
        if (!($cloudEvent instanceof CloudEvent)) {
            throw new SubscriptionException($eventClass . ' must be instance of CloudEvent');
        }

        $subscription = $event->subscription;
        $cloudEvent->setSubscription($subscription);
        $cloudEvent->setSequenceId($message->getSequence());

        $subscription->incrementReceived();

        if ($subscription->getMessageLimit() && $subscription->getReceived() >= $subscription->getMessageLimit()) {
            $connection = $this->subscriber->getConnection();

            $subscription->getTimeout() ? $connection->stopTimer($subscription->getSid()) : $connection->stop();
        }

        $this->dispatch($cloudEvent, $cloudEvent->getType());
    }
}
