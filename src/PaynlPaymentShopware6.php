<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6;

// phpcs:disable
require_once(__DIR__ . '/../vendor/autoload.php');
// phpcs:enable

use PaynlPayment\Shopware6\Helper\InstallHelper;
use Shopware\Core\Framework\Api\Controller\CacheController;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Throwable;

class PaynlPaymentShopware6 extends Plugin
{
    public function install(InstallContext $installContext): void
    {
        (new InstallHelper($this->container))->addPaynlMailTemplateText();
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        (new InstallHelper($this->container))->deactivatePaymentMethods($uninstallContext->getContext());
        if (!$uninstallContext->keepUserData()) {
            (new InstallHelper($this->container))->removePaymentMethodsMedia($uninstallContext->getContext());
            (new InstallHelper($this->container))->removeConfigurationData($uninstallContext->getContext());
            (new InstallHelper($this->container))->dropTables();
            (new InstallHelper($this->container))->removeStates();
            (new InstallHelper($this->container))->deletePaynlMailTemplateText();
        }
    }

    public function activate(ActivateContext $activateContext): void
    {
        (new InstallHelper($this->container))->activatePaymentMethods($activateContext->getContext());
    }

    public function update(UpdateContext $updateContext): void
    {
        (new InstallHelper($this->container))->removeAfterPayMedia($updateContext->getContext());
        (new InstallHelper($this->container))->updatePaymentMethods($updateContext->getContext());
        (new InstallHelper($this->container))->addPaynlMailTemplateText();

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
        (new InstallHelper($this->container))->deactivatePaymentMethods($deactivateContext->getContext());
    }
}
