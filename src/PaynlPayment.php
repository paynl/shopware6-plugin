<?php

declare(strict_types=1);

namespace PaynlPayment;

require_once (__DIR__ . '/../vendor/autoload.php');

use PaynlPayment\Helper\InstallHelper;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

class PaynlPayment extends Plugin
{
    public function install(InstallContext $installContext): void
    {
        (new InstallHelper($this->container))->addPaymentMethods($installContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        (new InstallHelper($this->container))->deactivatePaymentMethods($uninstallContext->getContext());
    }

    public function update(UpdateContext $updateContext): void
    {
        (new InstallHelper($this->container))->addPaymentMethods($updateContext->getContext());
    }

    public function activate(ActivateContext $activateContext): void
    {
        (new InstallHelper($this->container))->addPaymentMethods($activateContext->getContext());
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        (new InstallHelper($this->container))->deactivatePaymentMethods($deactivateContext->getContext());
        (new InstallHelper($this->container))->dropTables();
    }
}
