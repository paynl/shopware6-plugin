<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Checkout\ExpressCheckout\Service;

use PaynlPayment\Shopware6\Checkout\ExpressCheckout\PayPalExpressCheckoutButtonData;
use PaynlPayment\Shopware6\Checkout\ExpressCheckout\IdealExpressCheckoutButtonData;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Entity\PaynlTransactionEntity;
use PaynlPayment\Shopware6\Helper\LocaleCodeHelper;
use PaynlPayment\Shopware6\Repository\OrderCustomer\OrderCustomerRepository;
use PaynlPayment\Shopware6\Repository\PaymentMethod\PaymentMethodRepository;
use PaynlPayment\Shopware6\Repository\PaynlTransactions\PaynlTransactionsRepository;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RouterInterface;

class ExpressCheckoutDataService implements ExpressCheckoutDataServiceInterface
{
    private RouterInterface $router;
    private Config $config;
    private LocaleCodeHelper $localeCodeHelper;
    private PaymentMethodRepository $paymentMethodRepository;
    private OrderCustomerRepository $orderCustomerRepository;

    /**
     * @internal
     */
    public function __construct(
        RouterInterface $router,
        Config $config,
        LocaleCodeHelper $localeCodeHelper,
        PaymentMethodRepository $paymentMethodRepository,
        OrderCustomerRepository $orderCustomerRepository,
    ) {
        $this->router = $router;
        $this->config = $config;
        $this->localeCodeHelper = $localeCodeHelper;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->orderCustomerRepository = $orderCustomerRepository;
    }

    public function buildPayPalExpressCheckoutButtonData(
        SalesChannelContext $salesChannelContext,
        bool $addProductToCart = false
    ): ?PayPalExpressCheckoutButtonData {
        $context = $salesChannelContext->getContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $customer = $salesChannelContext->getCustomer();
        $loggedInCustomerEnabled = $this->config->getPaymentPayPalExpressLoggedInCustomerEnabled($salesChannelId);
        $this->isCompletedCustomerOrder($salesChannelContext);

        if (!$loggedInCustomerEnabled && $customer instanceof CustomerEntity && $customer->getActive()) {
            return null;
        }

        return (new PayPalExpressCheckoutButtonData())->assign([
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

    public function buildIdealExpressCheckoutButtonData(SalesChannelContext $salesChannelContext, bool $addProductToCart = false): ?IdealExpressCheckoutButtonData
    {
        $context = $salesChannelContext->getContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $customer = $salesChannelContext->getCustomer();
        $loggedInCustomerEnabled = $this->config->getPaymentIdealExpressLoggedInCustomerEnabled($salesChannelId);

        if (!$loggedInCustomerEnabled && $customer instanceof CustomerEntity && $customer->getActive()) {
            return null;
        }

        return (new IdealExpressCheckoutButtonData())->assign([
            'expressCheckoutEnabled' => $this->config->getPaymentIdealExpressCheckoutEnabled($salesChannelId),
            'expressShoppingCartEnabled' => $this->config->getPaymentIdealExpressShoppingCartEnabled($salesChannelId),
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

    private function isCompletedCustomerOrder(SalesChannelContext $salesChannelContext): bool
    {
        $customer = $salesChannelContext->getCustomer();
        if (! $customer) {
            return true;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customer_id', $salesChannelContext->getCustomerId()));

        $orderCustomer = $this->orderCustomerRepository->search($criteria, $salesChannelContext->getContext())->last();

        dd($orderCustomer);
    }
}
