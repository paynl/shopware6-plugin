<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Helper;

use Doctrine\DBAL\Connection;
use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Components\ConfigReader\ConfigReader;
use PaynlPayment\Shopware6\Entity\PaynlTransactionEntityDefinition;
use PaynlPayment\Shopware6\Enums\PayLaterPaymentMethodsEnum;
use PaynlPayment\Shopware6\Enums\StateMachineStateEnum;
use PaynlPayment\Shopware6\Exceptions\PaynlPaymentException;
use PaynlPayment\Shopware6\PaynlPaymentShopware6;
use PaynlPayment\Shopware6\Service\PaynlPaymentHandler;
use PaynlPayment\Shopware6\ValueObjects\PaymentMethodValueObject;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\CashPayment;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Session\Session;

class InstallHelper
{
    const MYSQL_DROP_TABLE = 'DROP TABLE IF EXISTS %s';

    const PAYMENT_METHOD_REPOSITORY_ID = 'payment_method.repository';
    const PAYMENT_METHOD_PAYNL = 'paynl_payment';
    const PAYMENT_METHOD_IDEAL_ID = 10;

    const SINGLE_PAYMENT_METHOD_ID = '123456789';

    /** @var SystemConfigService $configService */
    private $configService;
    /** @var PluginIdProvider $pluginIdProvider */
    private $pluginIdProvider;
    /** @var EntityRepositoryInterface $paymentMethodRepository */
    private $paymentMethodRepository;
    /** @var EntityRepositoryInterface $salesChannelRepository */
    private $salesChannelRepository;
    /** @var EntityRepositoryInterface $paymentMethodSalesChannelRepository */
    private $paymentMethodSalesChannelRepository;
    /** @var EntityRepositoryInterface */
    private $salesChannelPaymentMethodRepository;
    /** @var EntityRepositoryInterface $systemConfigRepository */
    private $systemConfigRepository;
    /** @var Connection $connection */
    private $connection;
    /** @var Api $paynlApi */
    private $paynlApi;
    /** @var MediaHelper $mediaHelper */
    private $mediaHelper;
    /** @var string */
    private $salesChannelId;

    public function __construct(ContainerInterface $container)
    {
        $this->pluginIdProvider = $container->get(PluginIdProvider::class);
        $this->paymentMethodRepository = $container->get(self::PAYMENT_METHOD_REPOSITORY_ID);
        $this->salesChannelRepository = $container->get('sales_channel.repository');
        $this->paymentMethodSalesChannelRepository = $container->get('sales_channel_payment_method.repository');
        $this->salesChannelPaymentMethodRepository = $container->get('sales_channel_payment_method.repository');
        $this->connection = $container->get(Connection::class);
        /** @var EntityRepositoryInterface $systemConfigRepository */
        $this->systemConfigRepository = $container->get('system_config.repository');

        // TODO:
        // plugin services doesn't registered on plugin install - create instances of classes
        // may be use setter injection?
        /** @var SystemConfigService $configService */
        $configService = $container->get(SystemConfigService::class);
        $this->configService = $configService;
        $config = new Config($configService, new ConfigReader($configService));
        /** @var EntityRepositoryInterface $customerAddressRepository */
        $customerAddressRepository = $container->get('customer_address.repository');
        /** @var EntityRepositoryInterface $customerRepository */
        $customerRepository = $container->get('customer.repository');
        $customerHelper = new CustomerHelper($config, $customerAddressRepository, $customerRepository);
        /** @var EntityRepositoryInterface $languageRepository */
        $languageRepository = $container->get('language.repository');
        /** @var RequestStack $requestStack */
        $requestStack = $container->get('request_stack');
        $transactionLanguageHelper = new TransactionLanguageHelper($config, $languageRepository, $requestStack);
        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $container->get('product.repository');
        /** @var EntityRepositoryInterface $orderRepository */
        $orderRepository = $container->get('order.repository');
        /** @var TranslatorInterface $translator */
        $translator = $container->get('translator');
        /** @var Session $session */
        $session = $container->get('session');
        $this->paynlApi = new Api(
            $config,
            $customerHelper,
            $transactionLanguageHelper,
            $productRepository,
            $orderRepository,
            $translator,
            $session
        );

        $this->mediaHelper = new MediaHelper($container);
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function installPaymentMethods(Context $context): void
    {
        if (empty($this->getSalesChannelId())
            || empty($this->getSalesChannelById($this->getSalesChannelId(), $context))
        ) {
            throw new PaynlPaymentException('Sales channel is empty');
        }
        $this->removeOldMedia($context);

        $paymentMethods = $this->paynlApi->getPaymentMethods($this->getSalesChannelId());
        if (empty($paymentMethods)) {
            throw new PaynlPaymentException('Cannot get any payment method.');
        }

        $this->deleteSalesChannelPaymentMethods($context);
        $this->upsertPaymentMethods($paymentMethods, $context);
    }

    public function updatePaymentMethods(Context $context): void
    {
        foreach ($this->getSalesChannelIds($context) as $salesChannelId) {
            $this->setSalesChannelId($salesChannelId);
            $this->installPaymentMethods($context);
        }
    }

    public function addSinglePaymentMethod(Context $context): void
    {
        $this->deleteSalesChannelPaymentMethods($context);
        $this->updateSinglePaymentMethod($context, true);

        $paymentMethodData[] = [
            API::PAYMENT_METHOD_ID => self::SINGLE_PAYMENT_METHOD_ID,
            API::PAYMENT_METHOD_NAME => 'Pay by PAY.',
            API::PAYMENT_METHOD_VISIBLE_NAME => 'Pay by PAY.',
            API::PAYMENT_METHOD_BRAND => [
                API::PAYMENT_METHOD_BRAND_DESCRIPTION => 'Pay by PAY.'
            ]
        ];

        $this->upsertPaymentMethods($paymentMethodData, $context);
    }

    public function removeSinglePaymentMethod(Context $context): void
    {
        $singlePaymentMethod = $this->getSinglePaymentMethod($context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $this->getSalesChannelId()));
        $criteria->addFilter(new EqualsFilter('paymentMethodId', $singlePaymentMethod->getId()));

        $salesChannelSinglePaymentMethod = $this->paymentMethodSalesChannelRepository->searchIds($criteria, $context);
        $isSalesChannelSinglePaymentMethodExist = (bool)$salesChannelSinglePaymentMethod->getIds();

        //add payment methods for sales channel
        if ($isSalesChannelSinglePaymentMethodExist) {
            $this->installPaymentMethods($context);
        }
    }

    private function updateSinglePaymentMethod(Context $context, bool $active): bool
    {
        /** @var PaymentMethodEntity $paymentMethod */
        $paymentMethod = $this->paymentMethodRepository->search(
            new Criteria([md5(self::SINGLE_PAYMENT_METHOD_ID)]),
            $context
        )->first();

        if (empty($paymentMethod)) {
            return false;
        }

        $pmData[] = [
            'id' => $paymentMethod->getId(),
            'active' => $active
        ];
        $this->paymentMethodRepository->upsert($pmData, $context);

        return true;
    }

    public function setDefaultPaymentMethod(Context $context, ?string $paymentMethodId = null): void
    {
        $salesChannelsToUpdate = [];

        if ($paymentMethodId === null) {
            /** @var PaymentMethodEntity $paymentMethod */
            $paymentMethod = $this->paymentMethodRepository->search(
                (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', CashPayment::class)),
                $context
            )->first();

            if (empty($paymentMethod)) {
                /** @var PaymentMethodEntity $paymentMethod */
                $paymentMethod = $this->paymentMethodRepository->search(
                    (new Criteria())
                        ->addFilter(new EqualsFilter('handlerIdentifier', PaynlPaymentHandler::class))
                        ->addFilter(new EqualsFilter('active', 1)),
                    $context
                )->first();
            }

            $paymentMethodId = $paymentMethod->getId();
        }

        $salesChannelsToUpdate[] = [
            'id' => $this->getSalesChannelId(),
            'paymentMethodId'=> $paymentMethodId
        ];

        $this->salesChannelRepository->upsert($salesChannelsToUpdate, $context);
    }

    private function getSinglePaymentMethod(Context $context): ?PaymentMethodEntity
    {
        return $this->paymentMethodRepository->search(
            new Criteria([md5(self::SINGLE_PAYMENT_METHOD_ID)]),
            $context
        )->first();
    }

    private function switchAllPaymentMethods(Context $context, bool $active): void
    {
        /** @var PaymentMethodCollection $paymentMethods */
        $paymentMethods = $this->paymentMethodRepository->search(new Criteria(), $context);
        $paymentMethodsNewData = [];

        /** @var PaymentMethodEntity $paymentMethod */
        foreach ($paymentMethods as $paymentMethod) {
            if ($paymentMethod->getId() === md5(self::SINGLE_PAYMENT_METHOD_ID)) {
                continue;
            }

            $paymentMethodsNewData[] = [
                'id' => $paymentMethod->getId(),
                'active' => $active
            ];
        }

        $this->paymentMethodRepository->update($paymentMethodsNewData, $context);
    }

    private function upsertPaymentMethods(array $paynlPaymentMethods, Context $context): void
    {
        $paymentMethods = [];
        $salesChannelsData = [];

//        $this->mediaHelper->removeOldMedia($context);
        foreach ($paynlPaymentMethods as $paymentMethod) {
            $paymentMethodValueObject = new PaymentMethodValueObject($paymentMethod);

            if (!empty($paymentMethodValueObject->getBrandId())) {
                $this->mediaHelper->addImageToMedia($paymentMethodValueObject, $context);
            }
            $paymentMethods[] = $this->getPaymentMethodData($context, $paymentMethodValueObject);

            $paymentMethodHashId = $paymentMethodValueObject->getHashedId();
            $salesChannelData = $this->getSalesChannelsData($context, $paymentMethodHashId, $this->getSalesChannelId());
            $salesChannelsData = array_merge($salesChannelsData, $salesChannelData);
        }

        $this->paymentMethodRepository->upsert($paymentMethods, $context);
        $this->paymentMethodSalesChannelRepository->upsert($salesChannelsData, $context);
    }

    /**
     * @param Context $context
     * @TODO to refactor the method
     */
    private function removeOldMedia(Context $context): void
    {
        $paynlPaymentMethodsIds = array_values($this->getPaynlPaymentMethods($context)->getIds());
        $criteria = new Criteria();
        $orFilter = [];
        foreach ($paynlPaymentMethodsIds as $paynlPaymentMethodId) {
            $orFilter[] = new EqualsFilter('paymentMethodId', $paynlPaymentMethodId);
        }
        $criteria->addFilter(new OrFilter($orFilter));

        $paymentMethodIds = $this->salesChannelPaymentMethodRepository->searchIds($criteria, $context)->getData();
        $salesChannelPaymentMethodIds = [];
        foreach ($paymentMethodIds as $paymentMethod) {
            if ($paymentMethod['sales_channel_id'] === $this->getSalesChannelId()) {
                $salesChannelPaymentMethodIds[] = $paymentMethod['payment_method_id'];
            }
        }

        $paymentMethodIds = array_filter($paymentMethodIds, function ($value) {
            if ($value['sales_channel_id'] === $this->getSalesChannelId()) {
                return false;
            }

            return true;
        });

        $paymentMethodIds = array_column($paymentMethodIds, 'payment_method_id');

        $paymentMethodIdsForRemove = array_diff($salesChannelPaymentMethodIds, $paymentMethodIds);
        if (empty($paymentMethodIdsForRemove)) {
            return;
        }

        $paymentMethods = $this->paymentMethodRepository->search(new Criteria($paymentMethodIdsForRemove), $context);
        $paymentMethodMediaIds = [];
        /** @var PaymentMethodEntity $paymentMethod */
        foreach ($paymentMethods as $paymentMethod) {
            if (!empty($paymentMethod->getMediaId())) {
                $paymentMethodMediaIds[] = $paymentMethod->getMediaId();
            }
        }

        $this->mediaHelper->removeOldMedia($context, $paymentMethodMediaIds);
    }

    private function getPaynlPaymentMethods(Context $context)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('handlerIdentifier', PaynlPaymentHandler::class));

        return $this->paymentMethodRepository->search($criteria, $context);
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
    private function getSalesChannelsData(Context $context, string $paymentMethodId, string $salesChannelId = ''): array
    {
        $criteria = new Criteria();
        if (!empty($salesChannelId)) {
            $criteria->addFilter(new EqualsFilter('id', $salesChannelId));
        }

        $channelsIds = $this->salesChannelRepository->searchIds($criteria, $context)->getIds();
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
            'name' => $paymentMethodValueObject->getVisibleName(),
            'description' => $paymentMethodValueObject->getDescription(),
            'pluginId' => $pluginId,
            'mediaId' => $this->mediaHelper->getMediaId($paymentMethodValueObject->getName(), $context),
            'afterOrderEnabled' => true,
            'active' => true,
            'customFields' => [
                self::PAYMENT_METHOD_PAYNL => 1,
                'banks' => $paymentMethodValueObject->getBanks()
            ]
        ];
        $paymentMethodId = $paymentMethodValueObject->getId();
        if ($paymentMethodId === self::PAYMENT_METHOD_IDEAL_ID) {
            $paymentData['customFields']['displayBanks'] = true;
        }

        if (in_array($paymentMethodId, PayLaterPaymentMethodsEnum::PAY_LATER_PAYMENT_METHODS)) {
            $paymentData['customFields']['isPayLater'] = true;
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
        $this->mediaHelper->removeOldMediaAll($context);
    }

    public function removeConfigurationData(Context $context): void
    {
        $criteria = (new Criteria())
            ->addFilter(new ContainsFilter('configurationKey', Config::CONFIG_DOMAIN));
        $idSearchResult = $this->systemConfigRepository->searchIds($criteria, $context);

        $ids = \array_map(static function ($id) {
            return ['id' => $id];
        }, $idSearchResult->getIds());

        $this->systemConfigRepository->delete($ids, $context);
    }

    private function changePaymentMethodsStatuses(Context $context, bool $active): void
    {
        $paynlPaymentMethods = $this->paymentMethodRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', PaynlPaymentHandler::class)),
            $context
        );
        $upsertData = [];
        /** @var PaymentMethodEntity $paymentMethod */
        foreach ($paynlPaymentMethods as $paymentMethod) {
            if ($active && $paymentMethod->getId() === md5(self::SINGLE_PAYMENT_METHOD_ID)) {
                continue;
            }

            $upsertData[] = [
                'id' => $paymentMethod->getId(),
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
        $stateMachineStateRefundingId = $this->connection->executeQuery($stateMachineStateSQl, [
            'technical_name' => StateMachineStateEnum::ACTION_REFUNDING
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
        $this->connection->executeUpdate($removeStateMachineTransitionSQL, [
            'to_state_id' => $stateMachineStateRefundingId,
            'from_state_id' => $stateMachineStateRefundingId
        ]);
    }

    private function deleteSalesChannelPaymentMethods(Context $context): void
    {
        if (empty($this->getSalesChannelId())) {
            throw new PaynlPaymentException();
        }
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $this->getSalesChannelId()));


        $salesChannelPaymentMethodIds = $this->salesChannelPaymentMethodRepository->searchIds($criteria, $context);
        if (empty($salesChannelPaymentMethodIds)) {
            throw new PaynlPaymentException();
        }

        $ids = array_map(function ($element) {
            return [
                'salesChannelId' => $element['sales_channel_id'],
                'paymentMethodId' => $element['payment_method_id']
            ];
        }, $salesChannelPaymentMethodIds->getData());

        $this->salesChannelPaymentMethodRepository->delete(array_values($ids), $context);
    }

    public function getSalesChannelIds(Context $context)
    {
        return $this->salesChannelRepository->search(new Criteria(), $context);
    }

    private function getSalesChannelById(string $id, Context $context)
    {
        return $this->salesChannelRepository->search(new Criteria([$id]), $context)->first();
    }
}
