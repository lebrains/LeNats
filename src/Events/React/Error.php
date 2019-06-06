<?php

namespace LeNats\Events\React;

use LeNats\Events\Event;

class Error extends Event
{
    /** @var string */
    public $error;

    public function __construct(string $error)
    {
        $this->error = $error;
    }
}
