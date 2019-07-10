<?php

namespace LeNats\Events\Nats;

use LeNats\Events\Event;

class Info extends Event
{
    /** @var array */
    public $message;

    public function __construct(string $message)
    {
        $this->message = json_decode($message, true);
    }
}
