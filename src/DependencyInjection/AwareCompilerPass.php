<?php

namespace LeNats\DependencyInjection;

use LeNats\Contracts\EventDispatcherAwareInterface;
use Symfony\Component\DependencyInjection\Compiler\AbstractRecursivePass;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class AwareCompilerPass extends AbstractRecursivePass
{
    protected function processValue($value, $isRoot = false)
    {
        $value = parent::processValue($value, $isRoot);

        if (!$value instanceof Definition || !$value->isAutowired() || $value->isAbstract() || !$value->getClass()) {
            return $value;
        }

        $reflection = $this->container->getReflectionClass($value->getClass());

        if ($reflection === null) {
            return $value;
        }

        if ($reflection->implementsInterface(EventDispatcherAwareInterface::class) && !$value->hasMethodCall('setDispatcher')) {
            $value->addMethodCall('setDispatcher', [new Reference('event_dispatcher')]);
        }

        if ($reflection->implementsInterface(ContainerAwareInterface::class) && !$value->hasMethodCall('setContainer')) {
            $value->addMethodCall('setContainer', [new Reference('service_container')]);
        }

        return $value;
    }
}
