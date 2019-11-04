<?php

declare(strict_types=1);

namespace PaynlPayment;

require_once (__DIR__ . '/../vendor/autoload.php');

use Doctrine\DBAL\Connection;
use Paynl\Paymentmethods;
use PaynlPayment\Components\Config;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
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
    }

    public function activate(ActivateContext $activateContext): void
    {
        $this->activatePaymentMethods($activateContext->getContext());
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        $this->deactivatePaymentMethods($deactivateContext->getContext());
        $this->dropTable(self::TABLE_PAYNL_TRANSACTIONS);
    }

    private function dropTable(string $tableName)
    {
        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);
        $connection->exec(sprintf(self::MYSQL_DROP_TABLE, $tableName));
    }

    private function addPaymentMethods(Context $context): void
    {
        $paynlPaymentMethods = $this->getPaynlPaymentMethods();
        foreach ($paynlPaymentMethods as $paymentMethod) {
            if (empty($this->getAppPaymentMethodId($paymentMethod[self::PAYMENT_METHOD_ID]))) {
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

        SDKConfig::setTokenCode($config->getTokenCode());
        SDKConfig::setApiToken($config->getApiToken());
        SDKConfig::setServiceId($config->getServiceId());

        return Paymentmethods::getList();
    }

    private function getAppPaymentMethodId(string $paymentMethodId): ?string
    {
        /** @var EntityRepositoryInterface $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');

        // Fetch ID for update
        $paymentCriteria = (new Criteria())
            ->addFilter(new EqualsFilter('handlerIdentifier', ExamplePayment::class));
        $paymentMethods = $paymentRepository->search($paymentCriteria, Context::createDefaultContext());

        if ($paymentMethods->getTotal() === 0) {
            return false;
        }

        foreach ($paymentMethods as $paymentMethod) {
            $paymentMethodActiveId = $paymentMethod->getCustomFields()['id'] ?? '';
            if ($paymentMethodId === $paymentMethodActiveId) {
                return $paymentMethod->getId();
            }
        }

        return null;
    }

    /**
     * @param mixed[] $paymentMethod
     */
    private function addPaymentMethod(Context $context, array $paymentMethod): void
    {
        /** @var PluginIdProvider $pluginIdProvider */
        $pluginIdProvider = $this->container->get(PluginIdProvider::class);
        $pluginId = $pluginIdProvider->getPluginIdByBaseClass($this->getClassName(), $context);
        $paymentData = [
            // payment handler will be selected by the identifier
            'handlerIdentifier' => ExamplePayment::class,
            'name' => $paymentMethod[self::PAYMENT_METHOD_NAME],
            'description' => 'Paynl payment method: ' . $paymentMethod[self::PAYMENT_METHOD_VISIBLE_NAME],
            'pluginId' => $pluginId,
            'customFields' => $paymentMethod,
        ];
        /** @var EntityRepositoryInterface $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');
        $paymentRepository->create([$paymentData], $context);
    }

    private function deactivatePaymentMethods(Context $context): void
    {
        $this->changePaymentMethodsStatuses($context, false);
    }

    private function activatePaymentMethods(Context $context): void
    {
        $this->changePaymentMethodsStatuses($context, true);
    }

    private function changePaymentMethodsStatuses(Context $context, bool $active): void
    {
        $paynlPaymentMethods = $this->getPaynlPaymentMethods();
        foreach ($paynlPaymentMethods as $paymentMethod) {
            if (empty($this->getAppPaymentMethodId($paymentMethod[self::PAYMENT_METHOD_ID]))) {
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
        $appPaymentMethodId = $this->getAppPaymentMethodId($paymentMethod[self::PAYMENT_METHOD_ID]);
        // nothing to update, payment method not found
        if (empty($appPaymentMethodId)) {
            return;
        }

        $paymentMethod = [
            'id' => $appPaymentMethodId,
            'active' => $active,
        ];
        $paymentRepository->update([$paymentMethod], $context);
    }
}
