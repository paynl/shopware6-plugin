<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Helper;

use Doctrine\DBAL\Connection;
use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Entity\PaynlTransactionEntityDefinition;
use PaynlPayment\Shopware6\Enums\StateMachineStateEnum;
use PaynlPayment\Shopware6\Exceptions\PaynlPaymentException;
use PaynlPayment\Shopware6\PaynlPaymentShopware6;
use PaynlPayment\Shopware6\Service\PaynlPaymentHandler;
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
    const PAYMENT_METHOD_DESCRIPTION_TPL = 'Paynl Payment method: %s';
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
        $this->mediaHelper = new MediaHelper($container);
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
                $this->mediaHelper->addMedia($paymentMethod, $context);
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
        $paymentMethodDescription = sprintf(
            self::PAYMENT_METHOD_DESCRIPTION_TPL,
            $paymentMethod[Api::PAYMENT_METHOD_VISIBLE_NAME]
        );
        $pluginId = $this->pluginIdProvider->getPluginIdByBaseClass(PaynlPaymentShopware6::class, $context);
        $paymentData = [
            'id' => $paymentMethodId,
            'handlerIdentifier' => PaynlPaymentHandler::class,
            'name' => $paymentMethodName,
            'description' => $paymentMethodDescription,
            'pluginId' => $pluginId,
            'mediaId' => $this->mediaHelper->getMediaId($paymentMethod, $context),
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
        $paynlPaymentConfigs = $this->configService->get('PaynlPaymentShopware6');
        if (isset($paynlPaymentConfigs['config'])) {
            foreach ($paynlPaymentConfigs['config'] as $configKey => $configName) {
                $this->configService->delete(sprintf(Config::CONFIG_TEMPLATE, $configKey));
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

    public function removeStates(): void
    {
        $stateMachineStateSQl = <<<SQL
SELECT id FROM state_machine_state WHERE technical_name = :technical_name LIMIT 1
SQL;
        $removeStateMachineTransitionSQL = <<<SQL
DELETE FROM state_machine_transition WHERE to_state_id = :to_state_id OR from_state_id = :from_state_id;
SQL;
        // Remove state machine state
        $stateMachineStateVerifyId = $this->connection->executeQuery($stateMachineStateSQl, [
            'technical_name' => StateMachineStateEnum::ACTION_VERIFY
        ])->fetchColumn();
        $stateMachineStateAuthorizeId = $this->connection->executeQuery($stateMachineStateSQl, [
            'technical_name' => StateMachineStateEnum::ACTION_AUTHORIZE
        ])->fetchColumn();
        $stateMachineStatePartlyCapturedId = $this->connection->executeQuery($stateMachineStateSQl, [
            'technical_name' => StateMachineStateEnum::ACTION_PARTLY_CAPTURED
        ])->fetchColumn();

        // Remove state machine transition
        $this->connection->executeUpdate($removeStateMachineTransitionSQL, [
            'to_state_id' => $stateMachineStateVerifyId,
            'from_state_id' => $stateMachineStateVerifyId
        ]);
        $this->connection->executeUpdate($removeStateMachineTransitionSQL, [
            'to_state_id' => $stateMachineStateAuthorizeId,
            'from_state_id' => $stateMachineStateAuthorizeId
        ]);
        $this->connection->executeUpdate($removeStateMachineTransitionSQL, [
            'to_state_id' => $stateMachineStatePartlyCapturedId,
            'from_state_id' => $stateMachineStatePartlyCapturedId
        ]);
    }
}
