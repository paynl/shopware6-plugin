<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Helper;

use Doctrine\DBAL\Connection;
use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Entity\PaynlTransactionEntityDefinition;
use PaynlPayment\Shopware6\Enums\PayLaterPaymentMethodsEnum;
use PaynlPayment\Shopware6\Enums\StateMachineStateEnum;
use PaynlPayment\Shopware6\Exceptions\PaynlPaymentException;
use PaynlPayment\Shopware6\PaymentHandler\Factory\PaymentHandlerFactory;
use PaynlPayment\Shopware6\PaynlPaymentShopware6;
use PaynlPayment\Shopware6\Service\PaynlPaymentHandler;
use PaynlPayment\Shopware6\Service\PaynlPaymentSyncHandler;
use PaynlPayment\Shopware6\ValueObjects\PaymentMethodValueObject;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\CashPayment;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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
    /** @var EntityRepositoryInterface $systemConfigRepository */
    private $systemConfigRepository;
    /** @var Connection $connection */
    private $connection;
    /** @var Api $paynlApi */
    private $paynlApi;
    /** @var MediaHelper $mediaHelper */
    private $mediaHelper;
    /** @var PaymentHandlerFactory */
    private $paymentHandlerFactory;

    public function __construct(ContainerInterface $container)
    {
        $this->pluginIdProvider = $container->get(PluginIdProvider::class);
        $this->paymentMethodRepository = $container->get(self::PAYMENT_METHOD_REPOSITORY_ID);
        $this->salesChannelRepository = $container->get('sales_channel.repository');
        $this->paymentMethodSalesChannelRepository = $container->get('sales_channel_payment_method.repository');
        $this->connection = $container->get(Connection::class);
        /** @var EntityRepositoryInterface $systemConfigRepository */
        $this->systemConfigRepository = $container->get('system_config.repository');

        // TODO:
        // plugin services doesn't registered on plugin install - create instances of classes
        // may be use setter injection?
        /** @var SystemConfigService $configService */
        $configService = $container->get(SystemConfigService::class);
        $this->configService = $configService;
        $config = new Config($configService);
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
        $this->paymentHandlerFactory = new PaymentHandlerFactory();
    }

    public function addPaymentMethods(Context $context): void
    {
        dd($this->paynlApi->getTerminals());
        $paynlPaymentMethods = $this->paynlApi->getPaymentMethods();
        if (empty($paynlPaymentMethods)) {
            throw new PaynlPaymentException("Cannot get any payment method.");
        }

        $this->upsertPaymentMethods($paynlPaymentMethods, $context);
    }

    public function updatePaymentMethods(Context $context): void
    {
        $paynlPaymentMethods = $this->paynlApi->getPaymentMethods();
        if (empty($paynlPaymentMethods)) {
            return;
        }

        $this->upsertPaymentMethods($paynlPaymentMethods, $context);
    }

    public function addSinglePaymentMethod(Context $context): void
    {
        $this->switchAllPaymentMethods($context, false);
        $update = $this->updateSinglePaymentMethod($context, true);

        if (!$update) {
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
    }

    public function removeSinglePaymentMethod(Context $context): void
    {
        $update = $this->updateSinglePaymentMethod($context, false);

        if ($update) {
            $this->switchAllPaymentMethods($context, true);
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
        $salesChannels = $this->salesChannelRepository->search(new Criteria(), $context);
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

        /** @var SalesChannelEntity $salesChannel */
        foreach ($salesChannels as $salesChannel) {
            $salesChannelsToUpdate[] = [
                'id' => $salesChannel->getId(),
                'paymentMethodId'=> $paymentMethodId
            ];
        }

        $this->salesChannelRepository->upsert($salesChannelsToUpdate, $context);
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

        $this->mediaHelper->removeOldMedia($context);
        foreach ($paynlPaymentMethods as $paymentMethod) {
            $paymentMethodValueObject = new PaymentMethodValueObject($paymentMethod);

            if (!empty($paymentMethodValueObject->getBrandId())) {
                $this->mediaHelper->addImageToMedia($paymentMethodValueObject, $context);
            }
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
        $paymentMethodHandler = $this->paymentHandlerFactory->get($paymentMethodValueObject->getId());

        $paymentData = [
            'id' => $paymentMethodValueObject->getHashedId(),
            'handlerIdentifier' => $paymentMethodHandler,
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
        $this->mediaHelper->removeOldMedia($context);
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
