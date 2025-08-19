<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PaymentMethod;

use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Helper\CustomerHelper;
use PaynlPayment\Shopware6\Helper\SettingsHelper;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PaymentMethodTerminalService
{
    private const COOKIE_PAY_NL_PIN_TERMINAL_ID = 'paynl_pin_terminal_id';
    private const ONE_YEAR_IN_SEC = 60 * 60 * 24 * 365;

    private Config $config;
    private CustomerHelper $customerHelper;

    public function __construct(Config $config, CustomerHelper $customerHelper)
    {
        $this->config = $config;
        $this->customerHelper = $customerHelper;
    }

    public function storeCustomerTerminal(
        PaymentMethodEntity $paymentMethod,
        SalesChannelContext $salesChannelContext,
        string $terminalId
    ): void {
        if (empty($terminalId)) {
            return;
        }

        $configTerminal = $this->config->getPaymentPinTerminal($salesChannelContext->getSalesChannel()->getId());

        $customer = $salesChannelContext->getCustomer();
        $context = $salesChannelContext->getContext();

        if ($customer === null) {
            return;
        }

        if (SettingsHelper::TERMINAL_CHECKOUT_SAVE_OPTION === $configTerminal) {
            $this->customerHelper->savePaynlInstoreTerminal($customer, $paymentMethod->getId(), $terminalId, $context);

            setcookie(self::COOKIE_PAY_NL_PIN_TERMINAL_ID, $terminalId, time() + self::ONE_YEAR_IN_SEC); //NOSONAR
        }
    }
}