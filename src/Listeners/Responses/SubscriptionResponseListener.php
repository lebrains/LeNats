<?php

namespace LeNats\Listeners\Responses;

use LeNats\Events\Nats\SubscriptionMessageReceived;
use NatsStreamingProtocol\SubscriptionResponse;

class SubscriptionResponseListener
{
    public function handle(SubscriptionMessageReceived $message): void
    {
        $response = new SubscriptionResponse();
        $response->mergeFromString($message->payload);

        $message->subscription->setAcknowledgeInbox($response->getAckInbox());
    }
}
