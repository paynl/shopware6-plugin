<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Checkout\ExpressCheckout\Service;

use PaynlPayment\Shopware6\Checkout\ExpressCheckout\ExpressCheckoutButtonData;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Repository\PaymentMethod\PaymentMethodRepository;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RouterInterface;

class PayPalExpressCheckoutDataService implements ExpressCheckoutDataServiceInterface
{
    private RouterInterface $router;
    private Config $config;
    private PaymentMethodRepository $paymentMethodRepository;

    /**
     * @internal
     */
    public function __construct(
        RouterInterface $router,
        Config $config,
        PaymentMethodRepository $paymentMethodRepository
    ) {
        $this->router = $router;
        $this->config = $config;
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    public function buildExpressCheckoutButtonData(
        SalesChannelContext $salesChannelContext,
        bool $addProductToCart = false
    ): ?ExpressCheckoutButtonData {
        $customer = $salesChannelContext->getCustomer();
        if ($customer instanceof CustomerEntity && $customer->getActive()) {
            return null;
        }

        $context = $salesChannelContext->getContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        return (new ExpressCheckoutButtonData())->assign([
            'expressCheckoutEnabled' => $this->config->getPaymentPayPalExpressCheckoutEnabled($salesChannelId),
            'expressShoppingCartEnabled' => $this->config->getPaymentPayPalExpressShoppingCartEnabled($salesChannelId),
            'clientId' => $this->config->getPaymentPayPalClientIdSandbox($salesChannelId),
            'currency' => $salesChannelContext->getCurrency()->getIsoCode(),
            'contextSwitchUrl' => $this->router->generate('frontend.account.PaynlPayment.paypal-express.prepare-cart'),
            'payPalPaymentMethodId' => $this->paymentMethodRepository->getActivePayPalID($context),
            'createOrderUrl' => $this->router->generate('frontend.account.PaynlPayment.paypal-express.start-payment'),
            'checkoutConfirmUrl' => $this->router->generate(
                'frontend.checkout.confirm.page',
                [],
                RouterInterface::ABSOLUTE_URL
            ),
            'cancelRedirectUrl' => $this->router->generate($addProductToCart ? 'frontend.checkout.cart.page' : 'frontend.checkout.register.page'),
        ]);
    }
}
