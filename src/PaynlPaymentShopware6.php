<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6;

// phpcs:disable
require_once(__DIR__ . '/../vendor/autoload.php');
// phpcs:enable

use Doctrine\DBAL\Connection;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Components\ConfigReader\ConfigReader;
use PaynlPayment\Shopware6\Helper\InstallHelper;
use PaynlPayment\Shopware6\Helper\MediaHelper;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class PaynlPaymentShopware6 extends Plugin
{
    public function uninstall(UninstallContext $uninstallContext): void
    {
        $this->getInstallHelper()->deactivatePaymentMethods($uninstallContext->getContext());
        if (!$uninstallContext->keepUserData()) {
            $this->getInstallHelper()->removePaymentMethodsMedia($uninstallContext->getContext());
            $this->getInstallHelper()->removeConfigurationData($uninstallContext->getContext());
            $this->getInstallHelper()->dropTables();
            $this->getInstallHelper()->removeStates();
        }
    }

    public function activate(ActivateContext $activateContext): void
    {
        $this->getInstallHelper()->activatePaymentMethods($activateContext->getContext());
    }

    public function update(UpdateContext $updateContext): void
    {
        $this->getInstallHelper()->updatePaymentMethods($updateContext->getContext());
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        $this->getInstallHelper()->deactivatePaymentMethods($deactivateContext->getContext());
    }

    private function getConfig(): Config
    {
        /** @var SystemConfigService $systemConfigService */
        $systemConfigService = $this->container->get(SystemConfigService::class);

        $configReader = new ConfigReader($systemConfigService);

        return new Config($configReader);
    }

    private function getInstallHelper(): InstallHelper
    {
        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);
        /** @var PluginIdProvider $pluginIdProvider */
        $pluginIdProvider = $this->container->get(PluginIdProvider::class);
        /** @var EntityRepositoryInterface $paymentMethodRepository */
        $paymentMethodRepository = $this->container->get('payment_method.repository');
        /** @var EntityRepositoryInterface $paymentMethodSalesChannelRepository */
        $paymentMethodSalesChannelRepository = $this->container->get('sales_channel_payment_method.repository');
        /** @var EntityRepositoryInterface $salesChannelRepository */
        $salesChannelRepository = $this->container->get('sales_channel.repository');
        /** @var EntityRepositoryInterface $systemConfigRepository */
        $systemConfigRepository = $this->container->get('system_config.repository');

        return new InstallHelper(
            $connection,
            $pluginIdProvider,
            $this->getConfig(),
            $this->getMediaHelper(),
            $paymentMethodRepository,
            $salesChannelRepository,
            $paymentMethodSalesChannelRepository,
            $systemConfigRepository
        );
    }

    private function getMediaHelper(): MediaHelper
    {
        /** @var FileSaver $fileSaver */
        $fileSaver = $this->container->get(FileSaver::class);
        /** @var EntityRepositoryInterface $mediaRepository */
        $mediaRepository = $this->container->get('media.repository');

        return new MediaHelper($fileSaver, $mediaRepository);
    }
}
