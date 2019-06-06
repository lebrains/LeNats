<?php

namespace LeNats\Support;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

trait Dispatcherable
{
    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @param EventDispatcherInterface $dispatcher
     */
    public function setDispatcher(EventDispatcherInterface $dispatcher): void
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param Event|string $eventObject
     * @param string|null  $eventName
     */
    public function dispatch($eventObject, ?string $eventName = null): void
    {
        if (is_string($eventObject)) {
            [$eventName, $eventObject] = [$eventObject, new Event()];
        }

        $this->dispatcher->dispatch($eventObject, $eventName);
    }
}
