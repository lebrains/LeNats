<?php

namespace LeNats\Support;

abstract class NatsEvents
{
    public const BUFFER_UPDATED = 'nats.buffer.updated';
    public const CONNECTING = 'nats.connecting';
    public const CONNECTED = 'nats.connected';
}
