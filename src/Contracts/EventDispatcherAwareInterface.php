<?php

namespace LeNats\Contracts;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

interface EventDispatcherAwareInterface
{
    public function setDispatcher(EventDispatcherInterface $dispatcher): void;
}
