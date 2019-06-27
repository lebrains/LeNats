<?php

namespace LeNats\Events;

use Psr\EventDispatcher\StoppableEventInterface;
use Symfony\Component\EventDispatcher\Event as BaseEvent;

abstract class Event extends BaseEvent implements StoppableEventInterface
{
    protected $propagationStopped = false;

    /**
     * @return bool
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * @deprecated since Symfony 4.3, use "Symfony\Contracts\EventDispatcher\Event" instead
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}
