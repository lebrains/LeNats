<?php

namespace LeNats\Tests\Mocks;

use LeNats\Subscription\Publisher;
use LeNats\Subscription\Subscriber;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class TestContainerPass implements CompilerPassInterface {
    private static $MUST_BE_PUBLIC = [
        Subscriber::class,
        Publisher::class,
    ];

    public function process(ContainerBuilder $container) {
        foreach ($container->getDefinitions() as $id => $definition) {
            if (in_array($id, self::$MUST_BE_PUBLIC, true)) {
                $definition->setPublic(true);
            }
        }
    }
}
