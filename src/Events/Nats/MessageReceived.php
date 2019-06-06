<?php

namespace LeNats\Events\Nats;

use LeNats\Events\Event;
use LeNats\Subscription\Subscription;

class MessageReceived extends Event
{
    /** @var string */
    public $payload;

    /**
     * @var Subscription
     */
    public $subscription;

    public function __construct(Subscription $subscription, string $payload)
    {
        $this->subscription = $subscription;
        $this->payload = $payload;
    }
}
