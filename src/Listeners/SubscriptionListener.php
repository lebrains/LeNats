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

        $cloudEvent->setSubscription($event->subscription);
        $cloudEvent->setSequenceId($message->getSequence());

        $event->subscription->incrementReceived();

        if ($event->subscription->getMessageLimit() && $event->subscription->getMessageLimit() >= $event->subscription->getReceived()) {
            $this->subscriber->unsubscribe($event->subscription->getSid());
        }

        $this->dispatch($cloudEvent, $cloudEvent->getType());
    }
}
