<?php

namespace LeNats\DependencyInjection;

use Exception;
use InvalidArgumentException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class LeNatsExtension extends Extension
{
    /**
     * Loads a specific configuration.
     * @inheritDoc
     *
     * @throws InvalidArgumentException When provided tag is not defined in this extension
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $configuration = $this->getConfiguration($configs, $container);

        if ($configuration === null) {
            throw  new Exception('Can\'t process configuration');
        }

        $config = $this->processConfiguration($configuration, $configs);

        $this->defineConfigurationService($config, $container);
    }

    private function defineConfigurationService(array $config, ContainerBuilder $container): void
    {
        $container->setParameter('lenats.configuration', $config['connection']);
        $container->setParameter('lenats.event.types', $config['accept_events'] ?? []);
        $container->setParameter('lenats.event.suffixes', $config['event_suffixes'] ?? []);
    }
}
