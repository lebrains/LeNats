<?php

namespace LeNats\Events\React;

use LeNats\Events\Event;

class Error extends Event
{
    public $error;

    public function __construct($error)
    {
        $this->error = $error;
    }
}
