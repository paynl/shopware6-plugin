<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Compatibility;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class DependencyLoader
{
    /**
     * @var Container
     */
    private $container;


    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @throws \Exception
     */
    public function loadServices(): void
    {
        /** @var string $version */
        $version = $this->container->getParameter('kernel.shopware_version');

        $versionCompare = new VersionCompare($version);


        /** @var ContainerBuilder $containerBuilder */
        $containerBuilder = $this->container;

        $loader = new XmlFileLoader($containerBuilder, new FileLocator(__DIR__ . '/../Resources/config'));

        $loader->load('services.xml');

//        if ($versionCompare->gte('6.5')) {
//            $loader->load('compatibility/snippets_6.5.xml');
//        } else {
//            $loader->load('compatibility/snippets.xml');
//        }

        if ($versionCompare->gte('6.5')) {
            $loader->load('compatibility/controller/sw65/controller.xml');
        } else {
            $loader->load('compatibility/controller/sw6/controller.xml');
        }
    }

    /**
     * @param string $pluginPath
     * @return string
     */
    public function getRoutesPath(string $pluginPath): string
    {
        /** @var string $version */
        $version = $this->container->getParameter('kernel.shopware_version');

        $versionCompare = new VersionCompare($version);

        if ($versionCompare->gte('6.5')) {
            return $pluginPath . '/Resources/config/compatibility/routes/sw65';
        }

        return $pluginPath . '/Resources/config/compatibility/routes/sw6';
    }
}
