<?php

namespace LeNats\Events\Nats;

use LeNats\Events\Event;

class Info extends Event
{
    public $message;

    public function __construct($message)
    {
        $this->message = $message;
    }
}
