<?php

namespace LeNats\Events\React;

use LeNats\Events\Event;

class Data extends Event
{
    public const NAME = 'nats.kernel.data';

    /** @var string */
    public $data;

    public function __construct(string $data)
    {
        $this->data = $data;
    }
}
