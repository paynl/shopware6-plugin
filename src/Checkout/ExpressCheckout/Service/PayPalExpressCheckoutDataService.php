<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Checkout\ExpressCheckout\Service;

use PaynlPayment\Shopware6\Checkout\ExpressCheckout\ExpressCheckoutButtonData;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Helper\LocaleCodeHelper;
use PaynlPayment\Shopware6\Repository\PaymentMethod\PaymentMethodRepository;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RouterInterface;

class PayPalExpressCheckoutDataService implements ExpressCheckoutDataServiceInterface
{
    private RouterInterface $router;
    private Config $config;
    private LocaleCodeHelper $localeCodeHelper;
    private PaymentMethodRepository $paymentMethodRepository;

    /**
     * @internal
     */
    public function __construct(
        RouterInterface $router,
        Config $config,
        LocaleCodeHelper $localeCodeHelper,
        PaymentMethodRepository $paymentMethodRepository
    ) {
        $this->router = $router;
        $this->config = $config;
        $this->localeCodeHelper = $localeCodeHelper;
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    public function buildExpressCheckoutButtonData(
        SalesChannelContext $salesChannelContext,
        bool $addProductToCart = false
    ): ?ExpressCheckoutButtonData {
        $context = $salesChannelContext->getContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $customer = $salesChannelContext->getCustomer();
        $loggedInCustomerEnabled = $this->config->getPaymentPayPalExpressLoggedInCustomerEnabled($salesChannelId);

        if (!$loggedInCustomerEnabled && $customer instanceof CustomerEntity && $customer->getActive()) {
            return null;
        }

        return (new ExpressCheckoutButtonData())->assign([
            'expressCheckoutEnabled' => $this->config->getPaymentPayPalExpressCheckoutEnabled($salesChannelId),
            'expressShoppingCartEnabled' => $this->config->getPaymentPayPalExpressShoppingCartEnabled($salesChannelId),
            'clientId' => $this->config->getPaymentPayPalClientIdSandbox($salesChannelId),
            'currency' => $salesChannelContext->getCurrency()->getIsoCode(),
            'languageIso' => $this->getInContextButtonLanguage($salesChannelContext->getContext()),
            'contextSwitchUrl' => $this->router->generate('frontend.account.PaynlPayment.paypal-express.prepare-cart'),
            'payPalPaymentMethodId' => $this->paymentMethodRepository->getActivePayPalID($context),
            'createOrderUrl' => $this->router->generate('frontend.account.PaynlPayment.paypal-express.start-payment'),
            'createPaymentUrl' => $this->router->generate('frontend.account.PaynlPayment.paypal-express.create-payment'),
            'checkoutConfirmUrl' => $this->router->generate(
                'frontend.checkout.confirm.page',
                [],
                RouterInterface::ABSOLUTE_URL
            ),
            'cancelRedirectUrl' => $this->router->generate($addProductToCart ? 'frontend.checkout.cart.page' : 'frontend.checkout.register.page'),
            'addErrorUrl' => $this->router->generate('frontend.account.PaynlPayment.paypal-express.add-error'),
        ]);
    }

    private function getInContextButtonLanguage(Context $context): string
    {
        return str_replace(
            '-',
            '_',
            $this->localeCodeHelper->getLocaleCodeFromContext($context)
        );
    }
}
