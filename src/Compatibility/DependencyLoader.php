<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Compatibility;

use Exception;
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
     * @throws Exception
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

    /**
     * @return void
     */
    public function prepareStorefrontBuild(): void
    {
        /** @var string $version */
        $version = $this->container->getParameter('kernel.shopware_version');

        $versionCompare = new VersionCompare($version);

        $pluginRoot = __DIR__ . '/../..';

        $distFileFolder = $pluginRoot . '/src/Resources/app/storefront/dist/storefront/js';

        if (!file_exists($distFileFolder)) {
            mkdir($distFileFolder, 0777, true);
        }

        if ($versionCompare->gte('6.5')) {
            $file = $pluginRoot . '/src/Resources/app/storefront/dist/paynl-payment-shopware6-65.js';
            $target = $distFileFolder . '/paynl-payment-shopware6.js';
        } else {
            $file = $pluginRoot . '/src/Resources/app/storefront/dist/paynl-payment-shopware6-64.js';
            $target = $distFileFolder . '/paynl-payment-shopware6.js';
        }

        if (file_exists($file) && !file_exists($target)) {
            copy($file, $target);
        }
    }
}
