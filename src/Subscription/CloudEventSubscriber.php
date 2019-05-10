<?php

namespace LeNats\Subscription;

use LeNats\Events\CloudEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class CloudEventSubscriber implements EventSubscriberInterface
{
    /**
     * @var Subscriber
     */
    private $subscriber;

    public function __construct(Subscriber $subscriber)
    {
        $this->subscriber = $subscriber;
    }

    protected function acknowledge(CloudEvent $event)
    {
        $this->subscriber->acknowledge($event);
    }
}
