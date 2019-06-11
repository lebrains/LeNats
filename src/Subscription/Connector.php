<?php

namespace LeNats\Subscription;

use Google\Protobuf\Internal\Message;
use LeNats\Listeners\HeartbeatListener;
use LeNats\Listeners\Responses\ConnectionResponseListener;
use NatsStreamingProtocol\ConnectRequest;

class Connector extends SubscriptionMessageStreamer
{
    protected function getRequest(Subscription $subscription): Message
    {
        $request = new ConnectRequest();
        $request->setClientID($this->connection->getConfig()->getClientId());
        $request->setHeartbeatInbox($subscription->getInbox());

        return $request;
    }

    protected function getPublishSubject(Subscription $subscription): string
    {
        return $subscription->getSubject();
    }

    protected function getMessageListenerClass(): string
    {
        return HeartbeatListener::class;
    }

    protected function getResponseListenerClass(): string
    {
        return ConnectionResponseListener::class;
    }
}
