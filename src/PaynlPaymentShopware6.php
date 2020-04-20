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
class PaynlPaymentShopware6 extends Plugin
{
    public function uninstall(UninstallContext $uninstallContext): void
    {
        (new InstallHelper($this->container))->deactivatePaymentMethods($uninstallContext->getContext());
        (new InstallHelper($this->container))->removeConfigurationData($uninstallContext->getContext());
        (new InstallHelper($this->container))->dropTables();
        (new InstallHelper($this->container))->removeStates();
    }

    public function activate(ActivateContext $activateContext): void
    {
        (new InstallHelper($this->container))->activatePaymentMethods($activateContext->getContext());
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        (new InstallHelper($this->container))->deactivatePaymentMethods($deactivateContext->getContext());
    }
}
