<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PayParts;

use PaynlPayment\Shopware6\Helper\LocaleCodeHelper;
use PaynlPayment\Shopware6\Repository\PaymentMethod\PaymentMethodRepository;
use PaynlPayment\Shopware6\ValueObjects\PayParts\Storefront\PayPartsCheckoutData;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RouterInterface;

class PayPartsStorefrontDataService
{
    private PaymentMethodRepository $paymentMethodRepository;
    private RouterInterface $router;
    private LocaleCodeHelper $localeCodeHelper;
    private SessionService $sessionService;

    public function __construct(
        PaymentMethodRepository $paymentMethodRepository,
        RouterInterface $router,
        LocaleCodeHelper $localeCodeHelper,
        SessionService $sessionService
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->router                  = $router;
        $this->localeCodeHelper        = $localeCodeHelper;
        $this->sessionService          = $sessionService;
    }

    /**
     * Builds PAY.Parts storefront data for the first-checkout flow (cart-based session).
     * Returns null when the credit card payment method is not among the page's available methods.
     */
    public function build(
        SalesChannelContext $context,
        PaymentMethodCollection $availablePaymentMethods
    ): ?PayPartsCheckoutData {
        $creditCardMethod = $this->paymentMethodRepository->findCreditCardInCollection($availablePaymentMethods);
        if ($creditCardMethod === null) {
            return null;
        }

        return (new PayPartsCheckoutData())->assign([
            'paymentMethodId'    => $creditCardMethod->getId(),
            'sessionUrl'         => $this->router->generate('frontend.PaynlPayment.payparts.session'),
            'createOrderUrl'     => $this->router->generate('frontend.PaynlPayment.payparts.create-order'),
            'linkTransactionUrl' => $this->router->generate('frontend.PaynlPayment.payparts.link-transaction'),
            'sdkUrl'             => $this->sessionService->getSdkUrl(),
            'country'            => $this->getCountryIso($context),
            'language'           => $this->getLanguageCode($context),
        ]);
    }

    /**
     * Builds PAY.Parts storefront data for the edit-order retry flow (order-based session).
     * Extends build() with the existing order's ID, open transaction ID and edit-order URL
     * so the JS plugin can skip order creation and pre-wire the error redirect.
     *
     * Returns null when credit card is not available or no open transaction exists on the order.
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

        $openTransaction = $this->findOpenTransaction($order);
        if ($openTransaction === null) {
            return null;
        }

        $data->assign([
            'orderId'            => $order->getId(),
            'orderTransactionId' => $openTransaction->getId(),
            'editOrderUrl'       => $this->router->generate(
                'frontend.account.edit-order.page',
                ['orderId' => $order->getId()]
            ),
        ]);

        return $data;
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function findOpenTransaction(OrderEntity $order): ?OrderTransactionEntity
    {
        foreach ($order->getTransactions() ?? [] as $transaction) {
            if ($transaction->getStateMachineState()?->getTechnicalName() === OrderTransactionStates::STATE_OPEN) {
                return $transaction;
            }
        }

        return null;
    }

    private function getCountryIso(SalesChannelContext $context): string
    {
        $customer = $context->getCustomer();
        if (!$customer instanceof CustomerEntity) {
            return PayPartsCheckoutData::DEFAULT_COUNTRY_ISO;
        }

        $countryIso = $customer->getActiveBillingAddress()?->getCountry()?->getIso();

        return $countryIso !== null && $countryIso !== ''
            ? strtoupper($countryIso)
            : PayPartsCheckoutData::DEFAULT_COUNTRY_ISO;
    }

    private function getLanguageCode(SalesChannelContext $context): string
    {
        $locale = $this->localeCodeHelper->getLocaleCodeFromContext($context->getContext());

        return strtolower(substr($locale, 0, 2));
    }
}

