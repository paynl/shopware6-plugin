<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Checkout\ExpressCheckout\Service;

use PaynlPayment\Shopware6\Checkout\ExpressCheckout\PayPalExpressCheckoutButtonData;
use PaynlPayment\Shopware6\Checkout\ExpressCheckout\IdealExpressCheckoutButtonData;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Enums\ExpressCheckoutEnum;
use PaynlPayment\Shopware6\Enums\PaynlTransactionStatusesEnum;
use PaynlPayment\Shopware6\Helper\LocaleCodeHelper;
use PaynlPayment\Shopware6\Repository\OrderCustomer\OrderCustomerRepository;
use PaynlPayment\Shopware6\Repository\PaymentMethod\PaymentMethodRepository;
use PaynlPayment\Shopware6\Repository\PaynlTransactions\PaynlTransactionsRepository;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RouterInterface;
use Exception;

class ExpressCheckoutDataService implements ExpressCheckoutDataServiceInterface
{
    private RouterInterface $router;
    private Config $config;
    private LocaleCodeHelper $localeCodeHelper;
    private LoggerInterface $logger;
    private PaymentMethodRepository $paymentMethodRepository;
    private OrderCustomerRepository $orderCustomerRepository;
    private PaynlTransactionsRepository $payTransactionRepository;

    /**
     * @internal
     */
    public function __construct(
        RouterInterface $router,
        Config $config,
        LocaleCodeHelper $localeCodeHelper,
        LoggerInterface $logger,
        PaymentMethodRepository $paymentMethodRepository,
        OrderCustomerRepository $orderCustomerRepository,
        PaynlTransactionsRepository $payTransactionRepository
    ) {
        $this->router = $router;
        $this->config = $config;
        $this->localeCodeHelper = $localeCodeHelper;
        $this->logger = $logger;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->orderCustomerRepository = $orderCustomerRepository;
        $this->payTransactionRepository = $payTransactionRepository;
    }

    public function buildPayPalExpressCheckoutButtonData(
        SalesChannelContext $salesChannelContext,
        bool $addProductToCart = false
    ): ?PayPalExpressCheckoutButtonData {
        $context = $salesChannelContext->getContext();
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        if (!$this->isPaymentValid($salesChannelContext)) {
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
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        if (!$this->isPaymentValid($salesChannelContext)) {
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
        if (!$customer) {
            return true;
        }

        $isTempCustomer = $customer->getEmail() === ExpressCheckoutEnum::CUSTOMER_EMAIL;

        try {
            $orderCustomerCriteria = new Criteria();
            $orderCustomerCriteria->addFilter(new EqualsFilter('customerId', $salesChannelContext->getCustomerId()));

            $orderCustomer = $this->orderCustomerRepository->search($orderCustomerCriteria, $salesChannelContext->getContext())->last();

            if (!$orderCustomer) {
                return !$isTempCustomer;
            }

            $payTransactionCriteria = (new Criteria());
            $payTransactionCriteria->addFilter(new EqualsFilter('orderTransaction.orderId', $orderCustomer->getOrderId()));
            $payTransactionCriteria->addFilter(new NotFilter('AND', [
                new EqualsAnyFilter('stateId', [
                    PaynlTransactionStatusesEnum::STATUS_CANCEL,
                    PaynlTransactionStatusesEnum::STATUS_EXPIRED,
                    PaynlTransactionStatusesEnum::STATUS_DENIED_63,
                    PaynlTransactionStatusesEnum::STATUS_DENIED_64,
                    PaynlTransactionStatusesEnum::STATUS_FAILURE,
                ])
            ]));
            $payTransactionCriteria->addAssociation('order');
            $payTransactionCriteria->addAssociation('orderTransaction.stateMachineState');
            $payTransactionCriteria->addAssociation('orderTransaction.order');

            $payTransaction = $this->payTransactionRepository->search($payTransactionCriteria, $salesChannelContext->getContext())->last();

            return !$isTempCustomer || !!$payTransaction;
        } catch (Exception $exception) {
            $this->logger->error('Guest customer express checkout: ' . $exception->getMessage(), [
                'exception' => $exception,
                'customerId' => $customer->getId()
            ]);

            return true;
        }
    }

    private function isPaymentValid(SalesChannelContext $salesChannelContext): bool
    {
        $salesChannelId = $salesChannelContext->getSalesChannelId();
        $loggedInCustomerEnabled = $this->config->getPaymentPayPalExpressLoggedInCustomerEnabled($salesChannelId);
        $customer = $salesChannelContext->getCustomer();
        $isCompletedCustomerOrder = $this->isCompletedCustomerOrder($salesChannelContext);

        if (
            !(
                !$isCompletedCustomerOrder ||
                $loggedInCustomerEnabled ||
                !($customer instanceof CustomerEntity) ||
                !$customer->getActive()
            )
        ) {
            return false;
        }

        return true;
    }
}
