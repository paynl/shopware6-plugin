<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Helper;

use Doctrine\DBAL\Connection;
use Paynl\Config as SDKConfig;
use Paynl\Paymentmethods;
use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Entity\PaynlTransactionEntityDefinition;
use PaynlPayment\Shopware6\Enums\PayLaterPaymentMethodsEnum;
use PaynlPayment\Shopware6\Enums\StateMachineStateEnum;
use PaynlPayment\Shopware6\Exceptions\PaynlPaymentException;
use PaynlPayment\Shopware6\PaymentHandler\Factory\PaymentHandlerFactory;
use PaynlPayment\Shopware6\PaynlPaymentShopware6;
use PaynlPayment\Shopware6\Repository\PaymentMethod\PaymentMethodRepositoryInterface;
use PaynlPayment\Shopware6\Repository\SalesChannel\SalesChannelRepositoryInterface;
use PaynlPayment\Shopware6\Repository\SalesChannelPaymentMethod\SalesChannelPaymentMethodRepositoryInterface;
use PaynlPayment\Shopware6\Repository\SystemConfig\SystemConfigRepositoryInterface;
use PaynlPayment\Shopware6\ValueObjects\PaymentMethodValueObject;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\CashPayment;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class InstallHelper
{
    const MYSQL_DROP_TABLE = 'DROP TABLE IF EXISTS %s';

    const PAYMENT_METHOD_PAYNL = 'paynl_payment';
    const PAYMENT_METHOD_IDEAL_ID = 10;

    const SINGLE_PAYMENT_METHOD_ID = '123456789';

    const PAYNL_PAYMENT_FILTER = 'PaynlPayment';

    const ORDER_TRANSACTION_PAID_MAIL_TEMPLATE = 'order_transaction.state.paid';

    const TPL_REG_EXP = '/\{\# PaynlPaymentShopware6\-pin\-start \#\}(.*?)\{\# PaynlPaymentShopware6\-pin\-end \#\}/s';

    const ID = 'id';
    const MAIL_TEMPLATE_ID = 'mail_template_id';
    const LANGUAGE_ID = 'language_id';
    const CONTENT_HTML = 'content_html';
    const CONTENT_PLAIN = 'content_plain';

    /** @var Connection $connection */
    private $connection;

    /** @var PluginIdProvider $pluginIdProvider */
    private $pluginIdProvider;

    /** @var Config */
    private $config;

    /** @var Api $paynlApi */
    private $paynlApi;

    /** @var MediaHelper $mediaHelper */
    private $mediaHelper;

    /** @var PaymentMethodRepositoryInterface $paymentMethodRepository */
    private $paymentMethodRepository;

    /** @var SalesChannelRepositoryInterface $salesChannelRepository */
    private $salesChannelRepository;

    /** @var SalesChannelPaymentMethodRepositoryInterface $paymentMethodSalesChannelRepository */
    private $paymentMethodSalesChannelRepository;

    /** @var SystemConfigRepositoryInterface $systemConfigRepository */
    private $systemConfigRepository;

    /** @var PaymentHandlerFactory */
    private $paymentHandlerFactory;

    public function __construct(
        Connection $connection,
        PluginIdProvider $pluginIdProvider,
        Config $config,
        Api $paynlApi,
        PaymentHandlerFactory $paymentHandlerFactory,
        MediaHelper $mediaHelper,
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        SalesChannelRepositoryInterface $salesChannelRepository,
        SalesChannelPaymentMethodRepositoryInterface $paymentMethodSalesChannelRepository,
        SystemConfigRepositoryInterface $systemConfigRepository
    ) {
        $this->pluginIdProvider = $pluginIdProvider;
        $this->connection = $connection;

        $this->config = $config;
        $this->paynlApi = $paynlApi;
        $this->mediaHelper = $mediaHelper;

        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->paymentMethodSalesChannelRepository = $paymentMethodSalesChannelRepository;
        $this->systemConfigRepository = $systemConfigRepository;
        $this->paymentHandlerFactory = $paymentHandlerFactory;
    }

    public function installPaymentMethods(string $salesChannelId, Context $context): void
    {
        if (empty($salesChannelId)
            || empty($this->getSalesChannelById($salesChannelId, $context))
        ) {
            throw new PaynlPaymentException('Sales channel is empty');
        }
        $this->removeOldMedia($salesChannelId, $context);

        $paymentMethods = $this->getPaynlPaymentMethods($salesChannelId);
        if (empty($paymentMethods)) {
            throw new PaynlPaymentException('Cannot get any payment method.');
        }

        $this->deleteSalesChannelPaymentMethods($salesChannelId, $context);
        $this->upsertPaymentMethods($paymentMethods, $salesChannelId, $context);
    }

    public function updatePaymentMethods(Context $context): void
    {
        foreach ($this->getSalesChannels($context)->getIds() as $salesChannelId) {
            if ($this->paynlApi->isValidStoredCredentials($salesChannelId)) {
                $this->installPaymentMethods($salesChannelId, $context);
            }
        }
    }

    public function addSinglePaymentMethod(string $salesChannelId, Context $context): void
    {
        $this->deleteSalesChannelPaymentMethods($salesChannelId, $context);
        $this->updateSinglePaymentMethod($context, true);

        $paymentMethodData[] = [
            API::PAYMENT_METHOD_ID => self::SINGLE_PAYMENT_METHOD_ID,
            API::PAYMENT_METHOD_NAME => 'Pay by PAY.',
            API::PAYMENT_METHOD_VISIBLE_NAME => 'Pay by PAY.',
            API::PAYMENT_METHOD_BRAND => [
                API::PAYMENT_METHOD_BRAND_DESCRIPTION => 'Pay by PAY.'
            ]
        ];

        $this->upsertPaymentMethods($paymentMethodData, $salesChannelId, $context);
    }

    public function removeSinglePaymentMethod(string $salesChannelId, Context $context): void
    {
        $singlePaymentMethod = $this->getSinglePaymentMethod($context);
        if (empty($singlePaymentMethod)) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        $criteria->addFilter(new EqualsFilter('paymentMethodId', $singlePaymentMethod->getId()));

        $salesChannelSinglePaymentMethod = $this->paymentMethodSalesChannelRepository->searchIds($criteria, $context);
        $isSalesChannelSinglePaymentMethodExist = (bool)$salesChannelSinglePaymentMethod->getIds();

        //add payment methods for sales channel
        if ($isSalesChannelSinglePaymentMethodExist) {
            $this->installPaymentMethods($salesChannelId, $context);
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

    public function setDefaultPaymentMethod(
        string $salesChannelId,
        Context $context,
        ?string $paymentMethodId = null
    ): void {
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
                        ->addFilter(new ContainsFilter('handlerIdentifier', self::PAYNL_PAYMENT_FILTER))
                        ->addFilter(new EqualsFilter('active', 1)),
                    $context
                )->first();
            }

            $paymentMethodId = $paymentMethod->getId();
        }

        $salesChannelsToUpdate[] = [
            'id' => $salesChannelId,
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

    private function upsertPaymentMethods(array $paynlPaymentMethods, string $salesChannelId, Context $context): void
    {
        $paymentMethods = [];
        $salesChannelsData = [];

        foreach ($paynlPaymentMethods as $paymentMethod) {
            $paymentMethodValueObject = new PaymentMethodValueObject($paymentMethod);

            if (!empty($paymentMethodValueObject->getBrandId())) {
                $this->mediaHelper->addImageToMedia($paymentMethodValueObject, $context);
            }
            $paymentMethods[] = $this->getPaymentMethodData($context, $paymentMethodValueObject);

            $paymentMethodHashId = $paymentMethodValueObject->getHashedId();
            $salesChannelData = $this->getSalesChannelsData($paymentMethodHashId, $salesChannelId, $context);
            $salesChannelsData = array_merge($salesChannelsData, $salesChannelData);
        }

        $this->paymentMethodRepository->upsert($paymentMethods, $context);
        $this->paymentMethodSalesChannelRepository->upsert($salesChannelsData, $context);
    }

    /**
     * @param Context $context
     */
    private function removeOldMedia(string $salesChannelId, Context $context): void
    {
        $paymentMethodIdsForRemoveMedia = $this->getPaymentMethodsForRemoveMedia($salesChannelId, $context);
        if (empty($paymentMethodIdsForRemoveMedia)) {
            return;
        }

        $paymentMethodMediaIds = [];
        /** @var PaymentMethodEntity $paymentMethod */
        foreach ($paymentMethodIdsForRemoveMedia as $paymentMethod) {
            if (!empty($paymentMethod->getMediaId())) {
                $paymentMethodMediaIds[] = $paymentMethod->getMediaId();
            }
        }

        $this->mediaHelper->removeOldMedia($context, $paymentMethodMediaIds);
    }

    /**
     * AfterPay rebranding to Riverty
     *
     * @param Context $context
     */
    public function removeAfterPayMedia(Context $context): void
    {
        $mediaId = $this->mediaHelper->getMediaId('AfterPay', $context);
        if (empty($mediaId)) {
            return;
        }

        $this->mediaHelper->removeOldMedia($context, [$mediaId]);
    }

    public function addSurchargePayStockImageMedia(Context $context): void
    {
        $this->mediaHelper->addSurchargePayStockImageMedia($context);
    }

    private function getPaymentMethodsForRemoveMedia(string $salesChannelId, Context $context): ?EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(new ContainsFilter('handlerIdentifier', self::PAYNL_PAYMENT_FILTER));
        $criteria->addAssociation('salesChannels');

        $paymentMethods = $this->paymentMethodRepository->search($criteria, $context);

        $orFilter = [];
        /** @var PaymentMethodEntity $paymentMethod */
        foreach ($paymentMethods as $paymentMethod) {
            /** @var SalesChannelEntity $salesChannel */
            foreach ($paymentMethod->getSalesChannels() as $salesChannel) {
                if ($salesChannel->getId() === $salesChannelId) {
                    continue;
                }
                $orFilter[] = new EqualsFilter('id', $paymentMethod->getId());
            }
        }

        $criteria = new Criteria();
        $criteria->addFilter(new ContainsFilter('handlerIdentifier', self::PAYNL_PAYMENT_FILTER));
        $criteria->addAssociation('salesChannels');
        $criteria->addFilter(
            new NotFilter(
                NotFilter::CONNECTION_OR,
                $orFilter
            )
        );
        $criteria->addFilter(
            new EqualsFilter('salesChannels.id', $salesChannelId)
        );

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
    private function getSalesChannelsData(string $paymentMethodId, string $salesChannelId, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $salesChannelId));

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
                'banks' => $paymentMethodValueObject->getBanks(),
                'paynlId' => $paymentMethodValueObject->getId()
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
            (new Criteria())->addFilter(new ContainsFilter('handlerIdentifier', self::PAYNL_PAYMENT_FILTER)),
            $context
        );
        $upsertData = [];
        /** @var PaymentMethodEntity $paymentMethod */
        foreach ($paynlPaymentMethods as $paymentMethod) {
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
        ])->fetchOne();
        $stateMachineStateAuthorizeId = $this->connection->executeQuery($stateMachineStateSQl, [
            'technical_name' => StateMachineStateEnum::ACTION_AUTHORIZE
        ])->fetchOne();
        $stateMachineStatePartlyCapturedId = $this->connection->executeQuery($stateMachineStateSQl, [
            'technical_name' => StateMachineStateEnum::ACTION_PARTLY_CAPTURED
        ])->fetchOne();
        $stateMachineStateRefundingId = $this->connection->executeQuery($stateMachineStateSQl, [
            'technical_name' => StateMachineStateEnum::ACTION_REFUNDING
        ])->fetchOne();

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

    private function deleteSalesChannelPaymentMethods(string $salesChannelId, Context $context): void
    {
        if (empty($salesChannelId)) {
            return;
        }

        // Filter for getting paynl payment methods by salesChannelId
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        $criteria->addAssociation('paymentMethod');
        $criteria->addFilter(new ContainsFilter('paymentMethod.handlerIdentifier', self::PAYNL_PAYMENT_FILTER));

        $salesChannelPaymentMethodIds = $this->paymentMethodSalesChannelRepository->searchIds($criteria, $context);
        if (empty($salesChannelPaymentMethodIds)) {
            return;
        }

        $ids = array_map(function ($element) {
            return [
                'salesChannelId' => $element['sales_channel_id'] ?? $element['salesChannelId'],
                'paymentMethodId' => $element['payment_method_id'] ?? $element['paymentMethodId']
            ];
        }, $salesChannelPaymentMethodIds->getData());

        $this->paymentMethodSalesChannelRepository->delete(array_values($ids), $context);
    }

    public function getSalesChannels(Context $context): ?EntitySearchResult
    {
        return $this->salesChannelRepository->search(new Criteria(), $context);
    }

    /**
     * @param string $id
     * @param Context $context
     * @return mixed|null
     */
    private function getSalesChannelById(string $id, Context $context)
    {
        return $this->salesChannelRepository->search(new Criteria([$id]), $context)->first();
    }

    private function getPaynlPaymentMethods(string $salesChannelId): array
    {
        SDKConfig::setTokenCode($this->config->getTokenCode($salesChannelId));
        SDKConfig::setApiToken($this->config->getApiToken($salesChannelId));
        SDKConfig::setServiceId($this->config->getServiceId($salesChannelId));

        return Paymentmethods::getList();
    }

    public function addPaynlMailTemplateText(): void
    {
        $mailTemplateTypeId = $this->getMailTemplateTypeId(self::ORDER_TRANSACTION_PAID_MAIL_TEMPLATE);
        $mailTemplates = $this->getMailTemplates($mailTemplateTypeId);

        foreach ($mailTemplates as $mailTemplate) {
            if (empty($mailTemplate[self::ID])) {
                continue;
            }

            $mailTemplateTranslations = $this->getMailTemplateTranslations($mailTemplate[self::ID]);

            foreach ($mailTemplateTranslations as $mailTemplateTranslation) {
                if (empty($mailTemplateTranslation[self::MAIL_TEMPLATE_ID])
                    || empty($mailTemplateTranslation[self::LANGUAGE_ID])) {
                    continue;
                }

                $mailContentHtml = $mailTemplateTranslation[self::CONTENT_HTML] ?? '';
                if (empty($this->searchMailTemplateText($mailContentHtml))) {
                    $this->updateMailTemplateTranslationContentHtml([
                        self::MAIL_TEMPLATE_ID => $mailTemplateTranslation[self::MAIL_TEMPLATE_ID],
                        self::LANGUAGE_ID => $mailTemplateTranslation[self::LANGUAGE_ID],
                        self::CONTENT_HTML => $this->generateMailTemplate($mailContentHtml)
                    ]);
                }

                $mailContentPlain = $mailTemplateTranslation[self::CONTENT_PLAIN] ?? '';
                if (empty($this->searchMailTemplateText($mailContentPlain))) {
                    $this->updateMailTemplateTranslationContentPlain([
                        self::MAIL_TEMPLATE_ID => $mailTemplateTranslation[self::MAIL_TEMPLATE_ID],
                        self::LANGUAGE_ID => $mailTemplateTranslation[self::LANGUAGE_ID],
                        self::CONTENT_PLAIN => $this->generateMailTemplate($mailContentPlain)
                    ]);
                }
            }
        }
    }

    public function deletePaynlMailTemplateText(): void
    {
        $mailTemplateTypeId = $this->getMailTemplateTypeId(self::ORDER_TRANSACTION_PAID_MAIL_TEMPLATE);
        $mailTemplates = $this->getMailTemplates($mailTemplateTypeId);

        foreach ($mailTemplates as $mailTemplate) {
            if (empty($mailTemplate[self::ID])) {
                continue;
            }

            $mailTemplateTranslations = $this->getMailTemplateTranslations($mailTemplate[self::ID]);

            foreach ($mailTemplateTranslations as $mailTemplateTranslation) {
                if (empty($mailTemplateTranslation[self::MAIL_TEMPLATE_ID])
                    || empty($mailTemplateTranslation[self::LANGUAGE_ID])
                ) {
                    continue;
                }

                $mailContentHtml = $mailTemplateTranslation[self::CONTENT_HTML] ?? '';
                $paynlMailTemplateBlockHtml = $this->searchMailTemplateText($mailContentHtml);
                if (!empty($paynlMailTemplateBlockHtml)) {
                    $mailContentHtml = str_replace($paynlMailTemplateBlockHtml, '', $mailContentHtml);
                    $this->updateMailTemplateTranslationContentHtml([
                        self::MAIL_TEMPLATE_ID => $mailTemplateTranslation[self::MAIL_TEMPLATE_ID],
                        self::LANGUAGE_ID => $mailTemplateTranslation[self::LANGUAGE_ID],
                        self::CONTENT_HTML => $mailContentHtml
                    ]);
                }

                $mailContentPlain = $mailTemplateTranslation[self::CONTENT_PLAIN] ?? '';
                $paynlMailTemplateBlockPlain = $this->searchMailTemplateText($mailContentPlain);
                if (!empty($paynlMailTemplateBlockPlain)) {
                    $mailContentPlain = str_replace($paynlMailTemplateBlockPlain, '', $mailContentPlain);
                    $this->updateMailTemplateTranslationContentPlain([
                        self::MAIL_TEMPLATE_ID => $mailTemplateTranslation[self::MAIL_TEMPLATE_ID],
                        self::LANGUAGE_ID => $mailTemplateTranslation[self::LANGUAGE_ID],
                        self::CONTENT_PLAIN => $mailContentPlain
                    ]);
                }
            }
        }
    }

    private function getPaynlMailTemplate(): string
    {
        return "{# PaynlPaymentShopware6-pin-start #}\n"
            . "{% set lastTransaction = order.transactions|last %}\n"
            . "{% for transaction in order.transactions %}\n"
            . "{% if transaction.stateMachineState.technicalName == \"paid\" %}\n"
            . "{% set lastTransaction = transaction %}\n"
            . "{% endif %}\n"
            . "{% endfor %}\n"
            . "{% if lastTransaction is not null and lastTransaction.customFields.paynl_payments.approval_id is defined %}\n"
            . "Auth. code - {{ lastTransaction.customFields.paynl_payments.approval_id }}\n"
            . "{% endif %}\n"
            . "{# PaynlPaymentShopware6-pin-end #}";
    }

    private function generateMailTemplate(string $shopwareMailTemplate): string
    {
        $shopwareMailTemplateLastChar = substr($shopwareMailTemplate, -1);
        $paynlMailTemplate = $this->getPaynlMailTemplate();
        if ($shopwareMailTemplateLastChar !== "\n") {
            $paynlMailTemplate = "\n{$paynlMailTemplate}";
        }

        return $shopwareMailTemplate . $paynlMailTemplate;
    }

    private function searchMailTemplateText(string $text): string
    {
        $matches = [];
        preg_match(self::TPL_REG_EXP, $text, $matches);

        return (string)reset($matches);
    }

    private function updateMailTemplateTranslationContentHtml(array $mailTemplateTranslationUpdate): void
    {
        $sqlQuery = implode(' ', [
            'UPDATE',
            'mail_template_translation',
            'SET',
            'content_html = :content_html, updated_at = CURRENT_TIME()',
            'WHERE',
            'mail_template_id = :mail_template_id',
            'AND',
            'language_id = :language_id',
            ';'
        ]);

        $this->connection->executeUpdate($sqlQuery, $mailTemplateTranslationUpdate);
    }

    private function updateMailTemplateTranslationContentPlain(array $mailTemplateTranslationUpdate): void
    {
        $sqlQuery = implode(' ', [
            'UPDATE',
            'mail_template_translation',
            'SET',
            'content_plain = :content_plain, updated_at = CURRENT_TIME()',
            'WHERE',
            'mail_template_id = :mail_template_id',
            'AND',
            'language_id = :language_id',
            ';'
        ]);

        $this->connection->executeUpdate($sqlQuery, $mailTemplateTranslationUpdate);
    }

    private function getMailTemplateTypeId(string $technicalName)
    {
        $sqlQuery = implode(' ', [
            'SELECT',
            'id',
            'FROM',
            'mail_template_type',
            'WHERE',
            'technical_name = :technical_name',
        ]);

        return $this->connection->executeQuery($sqlQuery, [
            'technical_name' => $technicalName,
        ])->fetchOne();
    }

    private function getMailTemplates(string $mailTemplateTypeId)
    {
        $sqlQuery = implode(' ', [
            'SELECT',
            'id',
            'FROM',
            'mail_template',
            'WHERE',
            'mail_template_type_id = :mail_template_type_id',
        ]);

        return $this->connection->executeQuery($sqlQuery, [
            'mail_template_type_id' => $mailTemplateTypeId,
        ])->fetchAll();
    }

    private function getMailTemplateTranslations(string $mailTemplateId)
    {
        $sqlQuery = implode(' ', [
            'SELECT',
            'mail_template_id, language_id, content_html, content_plain',
            'FROM',
            'mail_template_translation',
            'WHERE',
            'mail_template_id = :mail_template_id',
        ]);

        return $this->connection->executeQuery($sqlQuery, [
            'mail_template_id' => $mailTemplateId,
        ])->fetchAll();
    }
}
