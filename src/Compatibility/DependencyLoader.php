<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Compatibility;

use Symfony\Component\DependencyInjection\Container;

class DependencyLoader
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function prepareStorefrontBuild(): void
    {
        /** @var string $version */
        $version = $this->container->getParameter('kernel.shopware_version');

        $versionCompare = new VersionCompare($version);

        $pluginRoot = __DIR__ . '/../..';

        $distFileFolder = $pluginRoot . '/src/Resources/app/storefront/dist/storefront/js';
        if ($versionCompare->lt('6.6')) {
            $payPaymentShopwareJSPath = $distFileFolder . '/paynl-payment-shopware6/paynl-payment-shopware6.js';
            if (file_exists($payPaymentShopwareJSPath)) {
                unlink($payPaymentShopwareJSPath);
            }

            if (file_exists($distFileFolder . '/paynl-payment-shopware6')) {
                rmdir($distFileFolder . '/paynl-payment-shopware6');
            }
        }

        if ($versionCompare->gte('6.6')) {
            $distFileFolder .= '/paynl-payment-shopware6';
        }

        if (!file_exists($distFileFolder)) {
            mkdir($distFileFolder, 0777, true);
        }

        if ($versionCompare->gte('6.6')) {
            $file = $pluginRoot . '/src/Resources/app/storefront/dist/storefront/paynl-payment-shopware6-66.js';
            $target = $distFileFolder . '/paynl-payment-shopware6.js';
        } else {
            $file = $pluginRoot . '/src/Resources/app/storefront/dist/storefront/paynl-payment-shopware6-65.js';
            $target = $distFileFolder . '/paynl-payment-shopware6.js';
        }

        if (file_exists($file) && !file_exists($target)) {
            copy($file, $target);
        }
    }
}
