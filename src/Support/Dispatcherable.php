<?php

namespace LeNats\Support;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as NextEventDispatcherInterface;

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
     * @param Event       $event
     * @param string|null $eventName
     */
    public function dispatch(Event $event, ?string $eventName = null): void
    {
        $eventName = $eventName ?? get_class($event);

        if ($this->dispatcher instanceof NextEventDispatcherInterface) {
            $this->dispatcher->dispatch($event, $eventName);
        } else {
            $this->dispatcher->dispatch($eventName, $event);
        }
    }
}
