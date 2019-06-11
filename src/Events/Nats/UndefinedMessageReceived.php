<?php

namespace LeNats\Events\Nats;

use LeNats\Events\Event;
use LeNats\Subscription\Subscription;

class UndefinedMessageReceived extends Event
{
    /**
     * @var string
     */
    public $sid;

    /**
     * @var string
     */
    public $payload;

    /**
     * @var string
     */
    public $subject;

    /**
     * @var string|null
     */
    public $replay;

    public function __construct(string $sid, string $payload, string $subject, ?string $replay = null)
    {
        $this->sid = $sid;
        $this->payload = $payload;
        $this->subject = $subject;
        $this->replay = $replay;
    }

    public function toSubscribtion(Subscription $subscription): SubscriptionMessageReceived
    {
        return new SubscriptionMessageReceived($subscription, $this->payload);
    }
}
