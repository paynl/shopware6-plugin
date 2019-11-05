<?php

declare(strict_types=1);

namespace PaynlPayment\Helper;

use Doctrine\DBAL\Connection;
use PaynlPayment\Components\Api;
use PaynlPayment\Components\Config;
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
    const TABLE_PAYNL_TRANSACTIONS = 'paynl_transactions';

    const PAYMENT_METHOD_REPOSITORY_ID = 'payment_method.repository';
    const PAYMENT_METHOD_DESCRIPTION_TPL = 'Paynl payment method: %s';
    const PAYMENT_METHOD_PAYNL = 'paynl_payment';

    /** @var PluginIdProvider */
    private $pluginIdProvider;
    /** @var EntityRepositoryInterface */
    private $paymentMethodRepository;
    /** @var EntityRepositoryInterface */
    private $salesChannelRepository;
    /** @var EntityRepositoryInterface */
    private $paymentMethodSalesChannelRepository;
    /** @var Connection $connection */
    private $connection;
    /** @var Api */
    private $paynlApi;
    /** @var Config */
    private $config;

    public function __construct(ContainerInterface $container)
    {
        $this->pluginIdProvider = $container->get(PluginIdProvider::class);
        $this->paymentMethodRepository = $container->get(self::PAYMENT_METHOD_REPOSITORY_ID);
        $this->salesChannelRepository = $container->get('sales_channel.repository');
        $this->paymentMethodSalesChannelRepository = $container->get('sales_channel_payment_method.repository');
        $this->connection = $container->get(Connection::class);
        // plugin services doesn't registered on plugin install - create instances of classes
        // may be use setter injection?
        $this->config = new Config($container->get(SystemConfigService::class));
        $this->paynlApi = new Api($this->config);
    }

    public function addPaymentMethods(Context $context): void
    {
        $paynlPaymentMethods = $this->paynlApi->getPaymentMethods();
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
            // payment handler will be selected by the identifier
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

    private function changePaymentMethodsStatuses(Context $context, bool $active): void
    {
        $paynlPaymentMethods = $this->paynlApi->getPaymentMethods();
        foreach ($paynlPaymentMethods as $paymentMethod) {
            $shopwarePaymentMethodId = md5($paymentMethod[Api::PAYMENT_METHOD_ID]);
            if ($active && !$this->isInstalledPaymentMethod($shopwarePaymentMethodId)) {
                $this->addPaymentMethod($context, $paymentMethod);
            }
            $this->changePaymentMethodStatus($context, $paymentMethod, $active);
        }
    }

    /**
     * @param Context $context
     * @param mixed[] $paymentMethod
     * @param bool $active
     */
    private function changePaymentMethodStatus(Context $context, array $paymentMethod, bool $active): void
    {
        $shopwarePaymentMethodId = md5($paymentMethod[Api::PAYMENT_METHOD_ID]);
        if(!$this->isInstalledPaymentMethod($shopwarePaymentMethodId)) {
            return;
        }

        $data = [
            'id' => $shopwarePaymentMethodId,
            'active' => $active,
        ];
        $this->paymentMethodRepository->update([$data], $context);
    }

    public function dropTables(): void
    {
        $this->connection->exec(sprintf(self::MYSQL_DROP_TABLE, self::TABLE_PAYNL_TRANSACTIONS));
    }
}
