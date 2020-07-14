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
use PaynlPayment\Shopware6\ValueObjects\PaymentMethodValueObject;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class InstallHelper
{
    const MYSQL_DROP_TABLE = 'DROP TABLE IF EXISTS %s';

    const PAYMENT_METHOD_REPOSITORY_ID = 'payment_method.repository';
    const PAYMENT_METHOD_PAYNL = 'paynl_payment';
    const PAYMENT_METHOD_IDEAL_ID = 10;

    /** @var SystemConfigService $configService */
    private $configService;
    private $pluginIdProvider;
    private $paymentMethodRepository;
    private $salesChannelRepository;
    private $paymentMethodSalesChannelRepository;
    private $connection;
    /** @var Api */
    private $paynlApi;
    /** @var MediaHelper  */
    private $mediaHelper;

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
        /** @var EntityRepositoryInterface $customerAddressRepository */
        $customerAddressRepository = $container->get('customer_address.repository');
        $customerHelper = new CustomerHelper($config, $customerAddressRepository);
        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $container->get('product.repository');
        /** @var TranslatorInterface $translator */
        $translator = $container->get('translator');
        /** @var Session $session */
        $session = $container->get('session');
        $this->paynlApi = new Api($config, $customerHelper, $productRepository, $translator, $session);
        $this->mediaHelper = new MediaHelper($container);
    }

    public function addPaymentMethods(Context $context): void
    {
        $paynlPaymentMethods = $this->paynlApi->getPaymentMethods();
        if (empty($paynlPaymentMethods)) {
            throw new PaynlPaymentException("Cannot get any payment method.");
        }
        $paymentMethods = [];
        $salesChannelsData = [];

        foreach ($paynlPaymentMethods as $paymentMethod) {
            $paymentMethodValueObject = new PaymentMethodValueObject($paymentMethod);

            $this->mediaHelper->addImageToMedia($paymentMethodValueObject, $context);
            $paymentMethods[] = $this->getPaymentMethodData($context, $paymentMethodValueObject);
            $salesChannelData = $this->getSalesChannelsData($context, $paymentMethodValueObject->getHashedId());
            $salesChannelsData = array_merge($salesChannelsData, $salesChannelData);
        }

        $this->paymentMethodRepository->upsert($paymentMethods, $context);
        $this->paymentMethodSalesChannelRepository->upsert($salesChannelsData, $context);
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
     * @param string $paymentMethodId
     * @return mixed[]
     */
    private function getSalesChannelsData(Context $context, string $paymentMethodId): array
    {
        $channelsIds = $this->salesChannelRepository->searchIds(new Criteria(), $context)->getIds();
        $salesChannelsData = [];
        foreach ($channelsIds as $channelId) {
            $salesChannelsData[] = [
                'salesChannelId' => $channelId,
                'paymentMethodId' => $paymentMethodId,
            ];
        }

        return $salesChannelsData;
    }

    /**
     * @param Context $context
     * @param PaymentMethodValueObject $paymentMethodValueObject
     * @return mixed[]
     */
    private function getPaymentMethodData(Context $context, PaymentMethodValueObject $paymentMethodValueObject): array
    {
        $pluginId = $this->pluginIdProvider->getPluginIdByBaseClass(PaynlPaymentShopware6::class, $context);
        $paymentData = [
            'id' => $paymentMethodValueObject->getHashedId(),
            'handlerIdentifier' => PaynlPaymentHandler::class,
            'name' => $paymentMethodValueObject->getName(),
            'description' => $paymentMethodValueObject->getDescription(),
            'pluginId' => $pluginId,
            'mediaId' => $this->mediaHelper->getMediaId($paymentMethodValueObject->getName(), $context),
            'afterOrderEnabled' => true,
            'customFields' => [
                self::PAYMENT_METHOD_PAYNL => 1,
                'banks' => $paymentMethodValueObject->getBanks()
            ]
        ];
        if ($paymentMethodValueObject->getId() === self::PAYMENT_METHOD_IDEAL_ID) {
            $paymentData['customFields']['displayBanks'] = true;
        }

        return $paymentData;
    }

    public function deactivatePaymentMethods(Context $context): void
    {
        $this->changePaymentMethodsStatuses($context, false);
    }

    public function activatePaymentMethods(Context $context): void
    {
        $this->changePaymentMethodsStatuses($context, true);
    }

    public function removePaymentMethodsMedia(Context $context): void
    {
        $paynlPaymentMethods = $this->paynlApi->getPaymentMethods();
        if (empty($paynlPaymentMethods)) {
            return;
        }

        foreach ($paynlPaymentMethods as $paymentMethod) {
            $paymentMethodValueObject = new PaymentMethodValueObject($paymentMethod);
            $paymentMethodMediaId = $this->mediaHelper->getMediaIds($paymentMethodValueObject->getName(), $context);
            if ($paymentMethodMediaId) {
                $paymentMethodMediaId = array_map(static function ($id) {
                    return ['id' => $id];
                }, $paymentMethodMediaId);
                $this->mediaHelper->deleteMedia($paymentMethodMediaId, $context);
            }
        }
    }

    public function removeConfigurationData(): void
    {
        $paynlPaymentConfigs = $this->configService->get('PaynlPaymentShopware6');
        if (isset($paynlPaymentConfigs['settings'])) {
            foreach ($paynlPaymentConfigs['settings'] as $configKey => $configName) {
                $this->configService->delete(sprintf(Config::CONFIG_TEMPLATE, $configKey));
            }
        }
    }

    private function changePaymentMethodsStatuses(Context $context, bool $active): void
    {
        $paynlPaymentMethods = $this->paynlApi->getPaymentMethods();
        $upsertData = [];
        foreach ($paynlPaymentMethods as $paymentMethod) {
            $paymentMethodId = md5($paymentMethod[Api::PAYMENT_METHOD_ID]); //NOSONAR
            if (!$this->isInstalledPaymentMethod($paymentMethodId)) {
                continue;
            }

            $upsertData[] = [
                'id' => $paymentMethodId,
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
        $stateMachineStateSQl = join(' ' , [
            'SELECT',
            'id',
            'FROM',
            'state_machine_state',
            'WHERE',
            'technical_name = :technical_name',
            'LIMIT 1'
        ]);

        $removeStateMachineTransitionSQL = join(' ' , [
            'DELETE FROM',
            'state_machine_transition',
            'WHERE',
            'to_state_id = :to_state_id',
            'OR',
            'from_state_id = :from_state_id'
        ]);

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
