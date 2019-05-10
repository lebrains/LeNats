<?php

namespace LeNats\Listeners\Responses;

use LeNats\Events\Nats\MessageReceived;
use LeNats\Services\Configuration;
use NatsStreamingProtocol\ConnectResponse;

class ConnectionResponseListener
{
    /**
     * @var Configuration
     */
    private $config;

    public function __construct(Configuration $config)
    {
        $this->config = $config;
    }

    public function handle(MessageReceived $message): void
    {
        $response = new ConnectResponse();
        $response->mergeFromString($message->payload);

        $this->config->configureConnection($response);
    }
}
