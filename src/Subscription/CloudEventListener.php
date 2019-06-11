<?php

namespace LeNats\Subscription;

use LeNats\Events\CloudEvent;
use LeNats\Exceptions\ConnectionException;
use LeNats\Exceptions\StreamException;

abstract class CloudEventListener
{
    /**
     * @var Subscriber
     */
    private $subscriber;

    public function __construct(Subscriber $subscriber)
    {
        $this->subscriber = $subscriber;
    }

    /**
     * @param  CloudEvent          $event
     * @throws ConnectionException
     * @throws StreamException
     */
    protected function acknowledge(CloudEvent $event): void
    {
        $this->subscriber->acknowledge($event);
    }
}
