<?php

namespace LeNats\Events\Nats;

use LeNats\Events\Event;

class Info extends Event
{
    /** @var string */
    public $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }
}
