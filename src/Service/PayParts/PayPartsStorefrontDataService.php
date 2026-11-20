<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PayParts;

use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Helper\LocaleCodeHelper;
use PaynlPayment\Shopware6\Repository\PaymentMethod\PaymentMethodRepositoryInterface;
use PaynlPayment\Shopware6\ValueObjects\PayParts\Storefront\PayPartsCheckoutData;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RouterInterface;

class PayPartsStorefrontDataService
{
    private PaymentMethodRepositoryInterface $paymentMethodRepository;
    private RouterInterface $router;
    private LocaleCodeHelper $localeCodeHelper;
    private SessionService $sessionService;
    private Config $config;

    public function __construct(
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        RouterInterface $router,
        LocaleCodeHelper $localeCodeHelper,
        SessionService $sessionService,
        Config $config
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->router                  = $router;
        $this->localeCodeHelper        = $localeCodeHelper;
        $this->sessionService          = $sessionService;
        $this->config                  = $config;
    }

    /**
     * Builds PAY.Parts storefront data for the first-checkout flow (cart-based session).
     * Returns null when the credit card payment method is not among the page's available methods.
     */
    public function build(
        SalesChannelContext $context,
        PaymentMethodCollection $availablePaymentMethods
    ): ?PayPartsCheckoutData {
        $salesChannelId = $context->getSalesChannelId();

        if (!$this->config->isPayPartsCreditCardWidgetEnabled($salesChannelId)) {
            return null;
        }

        $creditCardMethod = $this->paymentMethodRepository
            ->findPayPartsCardMethodInCollection($availablePaymentMethods);

        if ($creditCardMethod === null) {
            return null;
        }

        return (new PayPartsCheckoutData())->assign([
            'paymentMethodId'    => $creditCardMethod->getId(),
            'sessionUrl'         => $this->router->generate('frontend.PaynlPayment.payparts.session'),
            'createOrderUrl'     => $this->router->generate('frontend.PaynlPayment.payparts.create-order'),
            'linkTransactionUrl' => $this->router->generate('frontend.PaynlPayment.payparts.link-transaction'),
            'reportIssueUrl'     => $this->router->generate('frontend.PaynlPayment.payparts.report-issue'),
            'sdkUrl'             => $this->sessionService->getSdkUrl(),
            'country'            => $this->getCountryIso($context),
            'language'           => $this->getLanguageCode($context),
        ]);
    }

    /**
     * Builds PAY.Parts storefront data for the edit-order retry flow (order-based session).
     * Adds the order ID and edit-order URL. The open card transaction is prepared by the create-order
     * endpoint when Pay is pressed (EditOrderTransactionService), so failed/cancelled/other-method
     * transactions are retryable too. Only called while the order's payment may still be changed.
     */
    public function buildForOrder(
        OrderEntity $order,
        SalesChannelContext $context,
        PaymentMethodCollection $availablePaymentMethods
    ): ?PayPartsCheckoutData {
        $data = $this->build($context, $availablePaymentMethods);
        if ($data === null) {
            return null;
        }

        $data->assign([
            'orderId'      => $order->getId(),
            'editOrderUrl' => $this->router->generate(
                'frontend.account.edit-order.page',
                ['orderId' => $order->getId()]
            ),
        ]);

        return $data;
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function getCountryIso(SalesChannelContext $context): string
    {
        $customer = $context->getCustomer();
        if (!$customer instanceof CustomerEntity) {
            return PayPartsCheckoutData::DEFAULT_COUNTRY_ISO;
        }

        $countryIso = $customer->getActiveBillingAddress()?->getCountry()?->getIso();

        if ($countryIso === null || $countryIso === '') {
            return PayPartsCheckoutData::DEFAULT_COUNTRY_ISO;
        }

        // The SDK only knows these countries (method ordering); everything else uses its DEFAULT order.
        $countryIso = strtoupper($countryIso);

        return in_array($countryIso, ['NL', 'BE', 'DE'], true) ? $countryIso : 'DEFAULT';
    }

    private function getLanguageCode(SalesChannelContext $context): string
    {
        $locale = $this->localeCodeHelper->getLocaleCodeFromContext($context->getContext());

        return strtolower(substr($locale, 0, 2));
    }
}

