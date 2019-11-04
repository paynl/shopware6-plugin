<?php

declare(strict_types=1);

namespace PaynlPayment;

require_once (__DIR__ . '/../vendor/autoload.php');

use Doctrine\DBAL\Connection;
use Paynl\Paymentmethods;
use PaynlPayment\Components\Config;
use PaynlPayment\Service\PaynlPaymentHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Paynl\Config as SDKConfig;

class PaynlPayment extends Plugin
{
    const MYSQL_DROP_TABLE = 'DROP TABLE IF EXISTS %s';
    const TABLE_PAYNL_TRANSACTIONS = 'paynl_transactions';

    const PAYMENT_METHOD_ID = 'id';
    const PAYMENT_METHOD_NAME = 'name';
    const PAYMENT_METHOD_VISIBLE_NAME = 'visibleName';

    const PAYMENT_METHOD_REPOSITORY_ID = 'payment_method.repository';
    const PAYMENT_METHOD_DESCRIPTION_TPL = 'Paynl payment method: %s';
    const PAYMENT_METHOD_PAYNL = 'paynl_payment';

    public function install(InstallContext $installContext): void
    {
        $this->addPaymentMethods($installContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        $this->deactivatePaymentMethods($uninstallContext->getContext());
    }

    public function update(UpdateContext $updateContext): void
    {
        $this->addPaymentMethods($updateContext->getContext());
    }

    public function activate(ActivateContext $activateContext): void
    {
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        $this->deactivatePaymentMethods($deactivateContext->getContext());
        $this->dropTable(self::TABLE_PAYNL_TRANSACTIONS);
    }

    private function dropTable(string $tableName): void
    {
        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);
        $connection->exec(sprintf(self::MYSQL_DROP_TABLE, $tableName));
    }

    private function addPaymentMethods(Context $context): void
    {
        $paynlPaymentMethods = $this->getPaynlPaymentMethods();
        foreach ($paynlPaymentMethods as $paymentMethod) {
            $shopwarePaymentMethodId = md5($paymentMethod[self::PAYMENT_METHOD_ID]);
            if (!$this->isInstalledPaymentMethod($shopwarePaymentMethodId)) {
                $this->addPaymentMethod($context, $paymentMethod);
            }
        }
    }

    /**
     * @return mixed[]
     */
    private function getPaynlPaymentMethods(): array
    {
        /** @var SystemConfigService $pluginConfig */
        $pluginConfig = $this->container->get(SystemConfigService::class);

        /** @var Config $config */
        $config = new Config($pluginConfig);

        // plugin doesn't configured, nothing to do
        if (empty($config->getTokenCode()) || empty($config->getApiToken()) || empty($config->getServiceId())) {
            return [];
        }

        SDKConfig::setTokenCode($config->getTokenCode());
        SDKConfig::setApiToken($config->getApiToken());
        SDKConfig::setServiceId($config->getServiceId());

        return Paymentmethods::getList();
    }

    private function isInstalledPaymentMethod(string $shopwarePaymentMethodId): bool
    {
        /** @var EntityRepositoryInterface $paymentRepository */
        $paymentRepository = $this->container->get(self::PAYMENT_METHOD_REPOSITORY_ID);
        // Fetch ID for update
        $paymentCriteria = (new Criteria())
            ->addFilter(new EqualsFilter('id', $shopwarePaymentMethodId));
        $paymentMethods = $paymentRepository->search($paymentCriteria, Context::createDefaultContext());

        return $paymentMethods->getTotal() !== 0;
    }

    /**
     * @param Context $context
     * @param mixed[] $paymentMethod
     * @throws InconsistentCriteriaIdsException
     */
    private function addPaymentMethod(Context $context, array $paymentMethod): void
    {
        /** @var PluginIdProvider $pluginIdProvider */
        $pluginIdProvider = $this->container->get(PluginIdProvider::class);
        $paymentMethodId = md5($paymentMethod[self::PAYMENT_METHOD_ID]);
        $paymentMethodName = $paymentMethod[self::PAYMENT_METHOD_NAME];
        $paymentMethodDescription =
            sprintf(self::PAYMENT_METHOD_DESCRIPTION_TPL, $paymentMethod[self::PAYMENT_METHOD_VISIBLE_NAME]);
        $pluginId = $pluginIdProvider->getPluginIdByBaseClass(PaynlPayment::class, $context);
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
        /** @var EntityRepositoryInterface $paymentMethodRepository */
        $paymentMethodRepository = $this->container->get(self::PAYMENT_METHOD_REPOSITORY_ID);
        $paymentMethodRepository->upsert([$paymentData], $context);

        /** @var EntityRepositoryInterface $salesChannelRepository */
        $salesChannelRepository = $this->container->get('sales_channel.repository');
        /** @var EntityRepositoryInterface $paymentMethodSalesChannelRepository */
        $paymentMethodSalesChannelRepository = $this->container->get('sales_channel_payment_method.repository');
        $channels = $salesChannelRepository->searchIds(new Criteria(), $context);
        foreach ($channels->getIds() as $channelId) {
            $data = [
                'salesChannelId'  => $channelId,
                'paymentMethodId' => $paymentMethodId,
            ];

            $paymentMethodSalesChannelRepository->upsert([$data], $context);
        }
    }

    private function deactivatePaymentMethods(Context $context): void
    {
        $this->changePaymentMethodsStatuses($context, false);
    }

    private function changePaymentMethodsStatuses(Context $context, bool $active): void
    {
        $paynlPaymentMethods = $this->getPaynlPaymentMethods();
        foreach ($paynlPaymentMethods as $paymentMethod) {
            $shopwarePaymentMethodId = md5($paymentMethod[self::PAYMENT_METHOD_ID]);
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
        /** @var EntityRepositoryInterface $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');
        $shopwarePaymentMethodId = md5($paymentMethod[self::PAYMENT_METHOD_ID]);

        if(!$this->isInstalledPaymentMethod($shopwarePaymentMethodId)) {
            return;
        }

        $data = [
            'id' => $shopwarePaymentMethodId,
            'active' => $active,
        ];
        $paymentRepository->update([$data], $context);
    }
}
