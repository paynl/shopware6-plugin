<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6;

// phpcs:disable
require_once(__DIR__ . '/../vendor/autoload.php');
// phpcs:enable

use PaynlPayment\Shopware6\Helper\InstallHelper;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

class PaynlPaymentShopware6 extends Plugin
{
    public function uninstall(UninstallContext $uninstallContext): void
    {
//        (new InstallHelper($this->container))->deactivatePaymentMethods($uninstallContext->getContext());
        $this->getInstallHelper()->deactivatePaymentMethods($uninstallContext->getContext());
        if (!$uninstallContext->keepUserData()) {
            $this->getInstallHelper()->removePaymentMethodsMedia($uninstallContext->getContext());
            $this->getInstallHelper()->removeConfigurationData($uninstallContext->getContext());
            $this->getInstallHelper()->dropTables();
            $this->getInstallHelper()->removeStates();

//            (new InstallHelper($this->container))->removePaymentMethodsMedia($uninstallContext->getContext());
//            (new InstallHelper($this->container))->removeConfigurationData($uninstallContext->getContext());
//            (new InstallHelper($this->container))->dropTables();
//            (new InstallHelper($this->container))->removeStates();
        }
    }

    public function activate(ActivateContext $activateContext): void
    {
        $this->getInstallHelper()->activatePaymentMethods($activateContext->getContext());
//        (new InstallHelper($this->container))->activatePaymentMethods($activateContext->getContext());
    }

    public function update(UpdateContext $updateContext): void
    {
        $this->getInstallHelper()->updatePaymentMethods($updateContext->getContext());
//        (new InstallHelper($this->container))->updatePaymentMethods($updateContext->getContext());
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        $this->getInstallHelper()->deactivatePaymentMethods($deactivateContext->getContext());
//        (new InstallHelper($this->container))->deactivatePaymentMethods($deactivateContext->getContext());
    }

    private function getInstallHelper(): InstallHelper
    {
        /** @var InstallHelper $installHelper */
        $installHelper = $this->container->get(InstallHelper::class);

        return $installHelper;
    }
}
