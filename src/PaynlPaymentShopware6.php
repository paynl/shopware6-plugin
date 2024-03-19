<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6;

// phpcs:disable
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once(__DIR__ . '/../vendor/autoload.php');
}
// phpcs:enable

use Doctrine\DBAL\Connection;
use Exception;
use PaynlPayment\Shopware6\Compatibility\DependencyLoader;
use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Components\ConfigReader\ConfigReader;
use PaynlPayment\Shopware6\Helper\CustomerHelper;
use PaynlPayment\Shopware6\Helper\InstallHelper;
use PaynlPayment\Shopware6\Helper\IpSettingsHelper;
use PaynlPayment\Shopware6\Helper\StringHelper;
use PaynlPayment\Shopware6\Helper\TransactionLanguageHelper;
use PaynlPayment\Shopware6\PaymentHandler\Factory\PaymentHandlerFactory;
use PaynlPayment\Shopware6\Repository\Customer\CustomerRepository;
use PaynlPayment\Shopware6\Repository\CustomerAddress\CustomerAddressRepository;
use PaynlPayment\Shopware6\Repository\Language\LanguageRepository;
use PaynlPayment\Shopware6\Repository\Media\MediaRepository;
use PaynlPayment\Shopware6\Repository\Order\OrderRepository;
use PaynlPayment\Shopware6\Repository\PaymentMethod\PaymentMethodRepository;
use PaynlPayment\Shopware6\Repository\Product\ProductRepository;
use PaynlPayment\Shopware6\Repository\SalesChannel\SalesChannelRepository;
use PaynlPayment\Shopware6\Repository\SalesChannelPaymentMethod\SalesChannelPaymentMethodRepository;
use PaynlPayment\Shopware6\Repository\SystemConfig\SystemConfigRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Api\Controller\CacheController;
use PaynlPayment\Shopware6\Helper\MediaHelper;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\Kernel;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class PaynlPaymentShopware6 extends Plugin
{
    public function install(InstallContext $installContext): void
    {
        $this->getInstallHelper()->addPaynlMailTemplateText();
        $this->getInstallHelper()->addSurchargePayStockImageMedia($installContext->getContext());
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
        $this->getInstallHelper()->removePaymentMethodOldLogos($updateContext->getContext());
        $this->getInstallHelper()->updatePaymentMethods($updateContext->getContext());
        $this->getInstallHelper()->addPaynlMailTemplateText();
        $this->getInstallHelper()->addSurchargePayStockImageMedia($updateContext->getContext());

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

    /**
     * @param ContainerBuilder $container
     * @throws Exception
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $this->container = $container;

        # load the dependencies that are compatible
        # with our current shopware version
        $loader = new DependencyLoader($this->container);
        $loader->loadServices();
        $loader->prepareStorefrontBuild();
    }

    /**
     * @param RoutingConfigurator $routes
     * @param string $environment
     * @return void
     */
    public function configureRoutes(RoutingConfigurator $routes, string $environment): void
    {
        if (!$this->isActive()) {
            return;
        }

        /** @var Container $container */
        $container = $this->container;

        $loader = new DependencyLoader($container);

        $routeDir = $loader->getRoutesPath($this->getPath());

        $fileSystem = new Filesystem();

        if ($fileSystem->exists($routeDir)) {
            $routes->import($routeDir . '/{routes}/*' . Kernel::CONFIG_EXTS, 'glob');
            $routes->import($routeDir . '/{routes}/' . $environment . '/**/*' . Kernel::CONFIG_EXTS, 'glob');
            $routes->import($routeDir . '/{routes}' . Kernel::CONFIG_EXTS, 'glob');
            $routes->import($routeDir . '/{routes}_' . $environment . Kernel::CONFIG_EXTS, 'glob');
        }
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
        /** @var EntityRepository $paymentMethodRepository */
        $paymentMethodRepository = $this->container->get('payment_method.repository');
        /** @var EntityRepository $paymentMethodSalesChannelRepository */
        $paymentMethodSalesChannelRepository = $this->container->get('sales_channel_payment_method.repository');
        /** @var EntityRepository $salesChannelRepository */
        $salesChannelRepository = $this->container->get('sales_channel.repository');
        /** @var EntityRepository $systemConfigRepository */
        $systemConfigRepository = $this->container->get('system_config.repository');

        return new InstallHelper(
            $connection,
            $pluginIdProvider,
            $this->getConfig(),
            $this->getPaynlApi(),
            $this->getPaymentHandlerFactory(),
            $this->getMediaHelper(),
            new PaymentMethodRepository($paymentMethodRepository),
            new SalesChannelRepository($salesChannelRepository),
            new SalesChannelPaymentMethodRepository($paymentMethodSalesChannelRepository),
            new SystemConfigRepository($systemConfigRepository)
        );
    }

    private function getPaynlApi(): Api
    {
        /** @var EntityRepository $productRepository */
        $productRepository = $this->container->get('product.repository');
        /** @var EntityRepository $orderRepository */
        $orderRepository = $this->container->get('order.repository');
        /** @var TranslatorInterface $translator */
        $translator = $this->container->get('translator');
        /** @var RequestStack $requestStack */
        $requestStack = $this->container->get('request_stack');

        return new Api(
            $this->getConfig(),
            $this->getCustomerHelper(),
            $this->getTransactionLanguageHelper(),
            new StringHelper(),
            new IpSettingsHelper($this->getConfig()),
            new ProductRepository($productRepository),
            new OrderRepository($orderRepository),
            $translator,
            $requestStack,
            $this->getLogger()
        );
    }

    private function getCustomerHelper(): CustomerHelper
    {
        /** @var EntityRepository $customerAddressRepository */
        $customerAddressRepository = $this->container->get('customer_address.repository');
        /** @var EntityRepository $customerRepository */
        $customerRepository = $this->container->get('customer.repository');

        return new CustomerHelper(
            $this->getConfig(),
            new CustomerAddressRepository($customerAddressRepository),
            new CustomerRepository($customerRepository)
        );
    }

    private function getMediaHelper(): MediaHelper
    {
        /** @var FileSaver $fileSaver */
        $fileSaver = $this->container->get(FileSaver::class);
        /** @var EntityRepository $mediaRepository */
        $mediaRepository = $this->container->get('media.repository');

        return new MediaHelper($fileSaver, new MediaRepository($mediaRepository));
    }

    private function getTransactionLanguageHelper(): TransactionLanguageHelper
    {
        /** @var EntityRepository $languageRepository */
        $languageRepository = $this->container->get('language.repository');
        /** @var RequestStack $requestStack */
        $requestStack = $this->container->get('request_stack');

        return new TransactionLanguageHelper(
            $this->getConfig(),
            new LanguageRepository($languageRepository),
            $requestStack
        );
    }

    private function getPaymentHandlerFactory(): PaymentHandlerFactory
    {
        return new PaymentHandlerFactory();
    }

    private function getLogger(): LoggerInterface
    {
        return new NullLogger();
    }
}
