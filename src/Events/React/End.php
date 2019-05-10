<?php

namespace LeNats\Events\React;

use LeNats\Events\Event;

class End extends Event
{
    /**
     * @var string
     */
    public $message;

    public function __construct(?string $message = null)
    {
        $this->message = $message;
    }
}
