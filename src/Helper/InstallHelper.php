<?php declare(strict_types=1);

namespace PaynlPayment\Helper;

use Doctrine\DBAL\Connection;
use PaynlPayment\Components\Api;
use PaynlPayment\Components\Config;
use PaynlPayment\Entity\PaynlTransactionEntityDefinition;
use PaynlPayment\Exceptions\PaynlPaymentException;
use PaynlPayment\PaynlPayment;
use PaynlPayment\Service\PaynlPaymentHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class InstallHelper
{
    const MYSQL_DROP_TABLE = 'DROP TABLE IF EXISTS %s';

    const PAYMENT_METHOD_REPOSITORY_ID = 'payment_method.repository';
    const PAYMENT_METHOD_DESCRIPTION_TPL = 'Paynl payment method: %s';
    const PAYMENT_METHOD_PAYNL = 'paynl_payment';

    /** @var SystemConfigService $configService */
    private $configService;
    private $pluginIdProvider;
    private $paymentMethodRepository;
    private $salesChannelRepository;
    private $paymentMethodSalesChannelRepository;
    private $connection;
    /** @var Api */
    private $paynlApi;

    public function __construct(ContainerInterface $container)
    {
        /** @var PluginIdProvider $this->pluginIdProvider  */
        $this->pluginIdProvider = $container->get(PluginIdProvider::class);
        /** @var EntityRepositoryInterface $this->paymentMethodRepository */
        $this->paymentMethodRepository = $container->get(self::PAYMENT_METHOD_REPOSITORY_ID);
        /** @var EntityRepositoryInterface $this->salesChannelRepository */
        $this->salesChannelRepository = $container->get('sales_channel.repository');
        /** @var EntityRepositoryInterface $this->paymentMethodSalesChannelRepository */
        $this->paymentMethodSalesChannelRepository = $container->get('sales_channel_payment_method.repository');
        /** @var Connection $this->connection */
        $this->connection = $container->get(Connection::class);
        // TODO:
        // plugin services doesn't registered on plugin install - create instances of classes
        // may be use setter injection?
        /** @var SystemConfigService $configService */
        $configService = $container->get(SystemConfigService::class);
        $this->configService = $configService;
        /** @var Config $config */
        $config = new Config($configService);
        $customerHelper = new CustomerHelper($config);
        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $container->get('product.repository');
        $this->paynlApi = new Api($config, $customerHelper, $productRepository);
    }

    public function addPaymentMethods(Context $context): void
    {
        $paynlPaymentMethods = $this->paynlApi->getPaymentMethods();
        if (empty($paynlPaymentMethods)) {
            throw new PaynlPaymentException("Cannot get any payment method.");
        }

        foreach ($paynlPaymentMethods as $paymentMethod) {
            $shopwarePaymentMethodId = md5($paymentMethod[Api::PAYMENT_METHOD_ID]);
            if (!$this->isInstalledPaymentMethod($shopwarePaymentMethodId)) {
                $this->addPaymentMethod($context, $paymentMethod);
            }
        }
    }

    private function isInstalledPaymentMethod(string $shopwarePaymentMethodId): bool
    {
        // Fetch ID for update
        $paymentCriteria = (new Criteria())
            ->addFilter(new EqualsFilter('id', $shopwarePaymentMethodId));
        $paymentMethods = $this->paymentMethodRepository->search($paymentCriteria, Context::createDefaultContext());

        return $paymentMethods->getTotal() !== 0;
    }

    /**
     * @param Context $context
     * @param mixed[] $paymentMethod
     * @throws InconsistentCriteriaIdsException
     */
    private function addPaymentMethod(Context $context, array $paymentMethod): void
    {
        $paymentMethodId = md5($paymentMethod[Api::PAYMENT_METHOD_ID]);
        $paymentMethodName = $paymentMethod[Api::PAYMENT_METHOD_NAME];
        $paymentMethodDescription =
            sprintf(self::PAYMENT_METHOD_DESCRIPTION_TPL, $paymentMethod[Api::PAYMENT_METHOD_VISIBLE_NAME]);
        $pluginId = $this->pluginIdProvider->getPluginIdByBaseClass(PaynlPayment::class, $context);
        $paymentData = [
            'id' => $paymentMethodId,
            'handlerIdentifier' => PaynlPaymentHandler::class,
            'name' => $paymentMethodName,
            'description' => $paymentMethodDescription,
            'pluginId' => $pluginId,
            'customFields' => [
                self::PAYMENT_METHOD_PAYNL => 1
            ]
        ];
        $this->paymentMethodRepository->upsert([$paymentData], $context);

        $channels = $this->salesChannelRepository->searchIds(new Criteria(), $context);
        foreach ($channels->getIds() as $channelId) {
            $data = [
                'salesChannelId'  => $channelId,
                'paymentMethodId' => $paymentMethodId,
            ];

            $this->paymentMethodSalesChannelRepository->upsert([$data], $context);
        }
    }

    public function deactivatePaymentMethods(Context $context): void
    {
        $this->changePaymentMethodsStatuses($context, false);
    }

    public function activatePaymentMethods(Context $context): void
    {
        $this->changePaymentMethodsStatuses($context, true);
    }

    public function removeConfigurationData(Context $context): void
    {
        $paynlPaymentConfigs = $this->configService->get('PaynlPayment');
        if (isset($paynlPaymentConfigs['config'])) {
            foreach ($paynlPaymentConfigs['config'] as $configKey => $configName) {
                $this->configService->delete(sprintf('PaynlPayment.config.%s', $configKey));
            }
        }
    }

    private function changePaymentMethodsStatuses(Context $context, bool $active): void
    {
        $paynlPaymentMethods = $this->paynlApi->getPaymentMethods();
        $upsertData = [];
        foreach ($paynlPaymentMethods as $paymentMethod) {
            $upsertData[] = [
                'id' => md5($paymentMethod[Api::PAYMENT_METHOD_ID]),
                'active' => $active,
            ];
        }

        if (!empty($upsertData)) {
            $this->paymentMethodRepository->upsert($upsertData, $context);
        }
    }

    public function dropTables(): void
    {
        $this->connection->exec(sprintf(self::MYSQL_DROP_TABLE, PaynlTransactionEntityDefinition::ENTITY_NAME));
    }
}
