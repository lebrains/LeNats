<?php

namespace LeNats\Listeners;

use JMS\Serializer\SerializerInterface;
use LeNats\Contracts\EventDispatcherAwareInterface;
use LeNats\Events\CloudEvent;
use LeNats\Events\Nats\MessageReceived;
use LeNats\Events\React\Error;
use LeNats\Exceptions\SubscriptionException;
use LeNats\Services\EventTypeResolver;
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

    public function __construct(SerializerInterface $serializer, EventTypeResolver $typeResolver)
    {
        $this->serializer = $serializer;
        $this->typeResolver = $typeResolver;
    }

    /**
     * @param  MessageReceived       $event
     * @throws SubscriptionException
     */
    public function handle(MessageReceived $event): void
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

        $this->dispatch($cloudEvent, $cloudEvent->getType());
    }
}
