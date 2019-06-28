<?php

namespace LeNats\Events;

use Symfony\Component\EventDispatcher\Event as BaseEvent;
use JMS\Serializer\Annotation as Serializer;

/**
 * @Serializer\ExclusionPolicy("ALL")
 */
abstract class Event extends BaseEvent
{
    /**
     * @var bool
     * @Serializer\Exclude()
     */
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
