<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6;

// phpcs:disable
require_once(__DIR__ . '/../vendor/autoload.php');
// phpcs:enable

use Doctrine\DBAL\Connection;
use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Components\ConfigReader\ConfigReader;
use PaynlPayment\Shopware6\Helper\CustomerHelper;
use PaynlPayment\Shopware6\Helper\InstallHelper;
use PaynlPayment\Shopware6\Helper\TransactionLanguageHelper;
use PaynlPayment\Shopware6\PaymentHandler\Factory\PaymentHandlerFactory;
use Shopware\Core\Framework\Api\Controller\CacheController;
use PaynlPayment\Shopware6\Helper\MediaHelper;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class PaynlPaymentShopware6 extends Plugin
{
    public function install(InstallContext $installContext): void
    {
        $this->getInstallHelper()->addPaynlMailTemplateText();
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        $this->getInstallHelper()->deactivatePaymentMethods($uninstallContext->getContext());
        if (!$uninstallContext->keepUserData()) {
            $this->getInstallHelper()->removePaymentMethodsMedia($uninstallContext->getContext());
            $this->getInstallHelper()->removeConfigurationData($uninstallContext->getContext());
            $this->getInstallHelper()->dropTables();
            $this->getInstallHelper()->removeStates();
            $this->getInstallHelper()->deletePaynlMailTemplateText();
        }
    }

    public function activate(ActivateContext $activateContext): void
    {
        $this->getInstallHelper()->activatePaymentMethods($activateContext->getContext());
    }

    public function update(UpdateContext $updateContext): void
    {
        $this->getInstallHelper()->removeAfterPayMedia($updateContext->getContext());
        $this->getInstallHelper()->updatePaymentMethods($updateContext->getContext());
        $this->getInstallHelper()->addPaynlMailTemplateText();

        try {
            $currentVersion = $this->container->getParameter('kernel.shopware_version');
            if (\version_compare($currentVersion, '6.4', '<')) {
                /** @var CacheController $cacheController */
                $cacheController = $this->container->get(CacheController::class);
                $cacheController->clearCacheAndScheduleWarmUp();
            }
        } catch (Throwable $exception) {

        }
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
            $this->getPaynlApi(),
            $this->getPaymentHandlerFactory(),
            $this->getMediaHelper(),
            $paymentMethodRepository,
            $salesChannelRepository,
            $paymentMethodSalesChannelRepository,
            $systemConfigRepository
        );
    }

    private function getPaynlApi(): Api
    {
        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->container->get('product.repository');
        /** @var EntityRepositoryInterface $orderRepository */
        $orderRepository = $this->container->get('order.repository');
        /** @var TranslatorInterface $translator */
        $translator = $this->container->get('translator');
        /** @var RequestStack $requestStack */
        $requestStack = $this->container->get('request_stack');

        return new Api(
            $this->getConfig(),
            $this->getCustomerHelper(),
            $this->getTransactionLanguageHelper(),
            $productRepository,
            $orderRepository,
            $translator,
            $requestStack
        );
    }

    private function getCustomerHelper(): CustomerHelper
    {
        /** @var EntityRepositoryInterface $customerAddressRepository */
        $customerAddressRepository = $this->container->get('customer_address.repository');
        /** @var EntityRepositoryInterface $customerRepository */
        $customerRepository = $this->container->get('customer.repository');

        return new CustomerHelper(
            $this->getConfig(),
            $customerAddressRepository,
            $customerRepository
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

    private function getTransactionLanguageHelper(): TransactionLanguageHelper
    {
        /** @var EntityRepositoryInterface $languageRepository */
        $languageRepository = $this->container->get('language.repository');
        /** @var RequestStack $requestStack */
        $requestStack = $this->container->get('request_stack');

        return new TransactionLanguageHelper(
            $this->getConfig(),
            $languageRepository,
            $requestStack
        );
    }

    private function getPaymentHandlerFactory(): PaymentHandlerFactory
    {
        return new PaymentHandlerFactory();
    }
}
