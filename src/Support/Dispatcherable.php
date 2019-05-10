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
     * @param string|Event $event
     * @param Event $eventObject
     */
    public function dispatch($event, ?Event $eventObject = null): void
    {
        $eventName = $event;

        if (is_object($event)) {
            $eventName = get_class($event);

            if ($event instanceof Event) {
                $eventObject = $event;
            }
        }

        $this->dispatcher->dispatch($eventName, $eventObject);
    }
}
