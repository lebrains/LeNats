<?php

namespace LeNats\Subscription;

use Google\Protobuf\Internal\Message;
use LeNats\Listeners\HeartbeatListener;
use LeNats\Listeners\Responses\ConnectionResponseListener;
use NatsStreamingProtocol\ConnectRequest;

class Connector extends Subscriber
{
    protected const MESSAGE_LISTENER = HeartbeatListener::class;
    protected const RESPONSE_LISTENER = ConnectionResponseListener::class;

    protected function getRequest(Subscription $subscription): Message
    {
        $request = new ConnectRequest();
        $request->setClientID($this->config->getClientId());
        $request->setHeartbeatInbox($subscription->getInbox());

        return $request;
    }

    protected function getPublishSubject(Subscription $subscription): string
    {
        return $subscription->getSubject();
    }
}
