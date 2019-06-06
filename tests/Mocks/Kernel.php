<?php

namespace LeNats\Tests\Mocks;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\RouteCollectionBuilder;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;
    private const CONFIG_EXTS = '.{php,xml,yaml,yml}';

    public function __construct($environment, $debug)
    {
        if (getenv('CLEAR_TEST_CACHE')) {
            $fs = new Filesystem();
            $fs->remove($this->getCacheDir());
        }

        parent::__construct($environment, $debug);
    }

    protected function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new TestContainerPass(), PassConfig::TYPE_OPTIMIZE);
    }

    public function registerBundles(): iterable
    {
        $contents = require $this->getConfigDir() . '/bundles.php';
        foreach ($contents as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                yield new $class();
            }
        }
    }

    public function getCacheDir()
    {
        return __DIR__ . '/cache';
    }

    public function getLogDir()
    {
        return $this->getCacheDir();
    }

    private function getConfigDir()
    {
        return __DIR__ . '/configV' . static::MAJOR_VERSION;
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->addResource(new FileResource($this->getConfigDir() . '/bundles.php'));
        $container->setParameter('container.dumper.inline_class_loader', true);
        $confDir = $this->getConfigDir();

        $loader->load($confDir . '/{packages}/*' . self::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/{packages}/' . $this->environment . '/**/*' . self::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/{services}' . self::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/{services}_' . $this->environment . self::CONFIG_EXTS, 'glob');
    }

    protected function configureRoutes(RouteCollectionBuilder $routes): void
    {
    }

    public function __sleep()
    {
        throw new \BadMethodCallException('Cannot serialize '.__CLASS__);
    }

    public function __wakeup()
    {
        throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
    }
}
