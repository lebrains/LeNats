<?php

namespace LeNats\Listeners\Responses;

use LeNats\Events\Nats\MessageReceived;
use LeNats\Services\Connection;
use NatsStreamingProtocol\SubscriptionResponse;

class SubscriptionResponseListener
{
    public function handle(MessageReceived $message): void
    {
        $response = new SubscriptionResponse();
        $response->mergeFromString($message->payload);

        $message->subscription->setAcknowledgeInbox($response->getAckInbox());
    }
}
