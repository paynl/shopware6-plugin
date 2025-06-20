<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Subscriber;

use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Entity\PaymentSurchargeEntity;
use PaynlPayment\Shopware6\Entity\PaynlTransactionEntity;
use PaynlPayment\Shopware6\Enums\PaynlTransactionStatusesEnum;
use PaynlPayment\Shopware6\Enums\StorefrontSubscriberEnum;
use PaynlPayment\Shopware6\Repository\PaynlTransactions\PaynlTransactionsRepositoryInterface;
use PaynlPayment\Shopware6\Service\PaymentMethod\PaymentMethodSurchargeService;
use PaynlPayment\Shopware6\ValueObjects\CustomPageDataValueObject;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoadedEvent;
use Shopware\Storefront\Page\Account\Profile\AccountProfilePageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PageLoadedSubscriber implements EventSubscriberInterface
{
    protected Config $config;
    protected PaymentMethodSurchargeService $surchargeService;
    protected SystemConfigService $systemConfigService;
    protected AbstractCartPersister $cartPersister;
    protected PaynlTransactionsRepositoryInterface $paynlTransactionRepository;

    private string $shopwareVersion;

    public function __construct(
        Config $config,
        PaymentMethodSurchargeService $surchargeService,
        SystemConfigService $systemConfigService,
        AbstractCartPersister $cartPersister,
        PaynlTransactionsRepositoryInterface $paynlTransactionRepository,
        string $shopwareVersion
    ) {
        $this->config = $config;
        $this->surchargeService = $surchargeService;
        $this->systemConfigService = $systemConfigService;
        $this->cartPersister = $cartPersister;
        $this->paynlTransactionRepository = $paynlTransactionRepository;
        $this->shopwareVersion = $shopwareVersion;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'onCheckoutConfirmPageLoaded',
            AccountEditOrderPageLoadedEvent::class => 'onAccountOrderEditPageLoaded',
            AccountProfilePageLoadedEvent::class => 'onAccountProfilePageLoaded',
            AccountPaymentMethodPageLoadedEvent::class => 'onAccountPaymentMethodPageLoaded',
            CheckoutFinishPageLoadedEvent::class => 'onCheckoutFinishedPageLoaded',
            'sales_channel.payment_method.loaded' => 'onPaymentMethodsLoaded',
        ];
    }

    public function onCheckoutConfirmPageLoaded(CheckoutConfirmPageLoadedEvent $checkoutConfirmPageLoadedEvent)
    {
        $salesChannelId = $checkoutConfirmPageLoadedEvent->getSalesChannelContext()->getSalesChannel()->getId();

        $checkoutConfirmPageLoadedEvent->getPage()->assign([
            'showDescription' => $this->config->getShowDescription($salesChannelId)
        ]);

        $configs = $this->systemConfigService->all($salesChannelId);
        $payNLCustomData = new CustomPageDataValueObject($configs, $this->shopwareVersion);

        $checkoutConfirmPageLoadedEvent->getPage()->addExtension(StorefrontSubscriberEnum::PAY_NL_DATA_EXTENSION_ID, $payNLCustomData);

        // Payment surcharging
        if ($this->config->isSurchargePaymentMethods($salesChannelId)) {
            $paymentMethods = $checkoutConfirmPageLoadedEvent->getPage()->getPaymentMethods();
            $chosenPaymentMethod = $checkoutConfirmPageLoadedEvent->getSalesChannelContext()->getPaymentMethod();

            $cart = $checkoutConfirmPageLoadedEvent->getPage()->getCart();

            $lineItems = $cart->getLineItems()->filter(function ($lineItem) {
                return $lineItem->getType() !== PaymentMethodSurchargeService::LINE_ITEM_TYPE;
            });

            $surchargeApplied = $cart->getLineItems()
                    ->filterType(PaymentMethodSurchargeService::LINE_ITEM_TYPE)
                    ->count() > 0;

            $this->calculatePaymentSurcharges(
                $paymentMethods,
                $chosenPaymentMethod,
                $lineItems,
                $cart->getPrice()->getTotalPrice(),
                $surchargeApplied,
                $checkoutConfirmPageLoadedEvent->getSalesChannelContext()
            );

            $checkoutConfirmPageLoadedEvent->getPage()->setPaymentMethods($paymentMethods);
        }
    }

    public function onAccountPaymentMethodPageLoaded(
        AccountPaymentMethodPageLoadedEvent $accountPaymentMethodPageLoadedEvent
    ) {
        $salesChannelId = $accountPaymentMethodPageLoadedEvent->getSalesChannelContext()->getSalesChannel()->getId();

        $accountPaymentMethodPageLoadedEvent->getPage()->assign([
            'showDescription' => $this->config->getShowDescription($salesChannelId)
        ]);

        $configs = $this->systemConfigService->all($salesChannelId);
        $payNLCustomData = new CustomPageDataValueObject($configs, $this->shopwareVersion);

        $accountPaymentMethodPageLoadedEvent->getPage()->addExtension(StorefrontSubscriberEnum::PAY_NL_DATA_EXTENSION_ID, $payNLCustomData);
    }

    public function onAccountOrderEditPageLoaded(AccountEditOrderPageLoadedEvent $accountEditOrderPageLoadedEvent)
    {
        $salesChannelContext = $accountEditOrderPageLoadedEvent->getSalesChannelContext();
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();

        $accountEditOrderPageLoadedEvent->getPage()->assign([
            'showDescription' => $this->config->getShowDescription($salesChannelId)
        ]);

        $configs = $this->systemConfigService->all($salesChannelId);
        $payNLCustomData = new CustomPageDataValueObject($configs, $this->shopwareVersion);

        $accountEditOrderPageLoadedEvent->getPage()->addExtension(StorefrontSubscriberEnum::PAY_NL_DATA_EXTENSION_ID, $payNLCustomData);

        // Payment surcharging
        if ($this->config->isSurchargePaymentMethods($salesChannelId)) {

            $order = $accountEditOrderPageLoadedEvent->getPage()->getOrder();
            try {
                $accountEditOrderPageLoadedEvent->getPage()->setOrder(
                    $this->surchargeService->updateOrderTotal(
                        $order,
                        $accountEditOrderPageLoadedEvent->getSalesChannelContext(),
                        true
                    )
                );
            } catch (EntityNotFoundException $e) {
                return;
            }

            $chosenPaymentMethod = $accountEditOrderPageLoadedEvent->getSalesChannelContext()->getPaymentMethod();
            $paymentMethods = $accountEditOrderPageLoadedEvent->getPage()->getPaymentMethods();

            $lineItems = $order->getLineItems()->filter(function ($lineItem) {
                return $lineItem->getType() !== PaymentMethodSurchargeService::LINE_ITEM_TYPE;
            });

            $this->calculatePaymentSurcharges(
                $paymentMethods,
                $chosenPaymentMethod,
                $lineItems,
                $order->getPrice()->getTotalPrice(),
                $order->getLineItems()->filterByType(PaymentMethodSurchargeService::LINE_ITEM_TYPE)->count() > 0,
                $salesChannelContext
            );
        }
    }

    public function onPaymentMethodsLoaded(SalesChannelEntityLoadedEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();
        if (!$this->config->isSurchargePaymentMethods($salesChannelContext->getSalesChannel()->getId())) {
            return;
        }

        try {
            $cart = $this->cartPersister->load(
                $event->getSalesChannelContext()->getToken(),
                $event->getSalesChannelContext()
            );
        } catch (CartTokenNotFoundException $e) {
            return;
        }

        $paymentMethods = $event->getEntities();
        $chosenPaymentMethod = $salesChannelContext->getPaymentMethod();

        $lineItems = $cart->getLineItems()->filter(function ($lineItem) {
            return $lineItem->getType() !== PaymentMethodSurchargeService::LINE_ITEM_TYPE;
        });

        $surchargeApplied = $cart->getLineItems()
                ->filterType(PaymentMethodSurchargeService::LINE_ITEM_TYPE)
                ->count() > 0;

        $this->calculatePaymentSurcharges(
            new PaymentMethodCollection($paymentMethods),
            $chosenPaymentMethod,
            $lineItems,
            $cart->getPrice()->getTotalPrice(),
            $surchargeApplied,
            $salesChannelContext
        );
    }

    public function onCheckoutFinishedPageLoaded(CheckoutFinishPageLoadedEvent $event): void
    {
        $this->addPaynlTransactionStatus($event);

        $salesChannelContext = $event->getSalesChannelContext();
        if (!$this->config->isSurchargePaymentMethods($salesChannelContext->getSalesChannel()->getId())) {
            return;
        }

        $paymentSurchargeLineItem = $event->getPage()
            ->getOrder()
            ->getLineItems()
            ->filterByType(PaymentMethodSurchargeService::LINE_ITEM_TYPE)
            ->first();
        $chosenPaymentMethod = $event->getSalesChannelContext()->getPaymentMethod();

        if ($paymentSurchargeLineItem !== null) {
            $chosenPaymentMethod->addExtension(
                PaymentMethodSurchargeService::PAYMENT_SURCHARGE_EXTENSION,
                new ArrayStruct([
                    'surcharge_amount' => $paymentSurchargeLineItem->getTotalPrice(),
                    ]
                )
            );
        }
    }

    public function onAccountProfilePageLoaded(AccountProfilePageLoadedEvent $event): void
    {
        $configs = $this->systemConfigService->all($event->getSalesChannelContext()->getSalesChannelId());
        $payNLCustomData = new CustomPageDataValueObject($configs, $this->shopwareVersion);

        $event->getPage()->addExtension(StorefrontSubscriberEnum::PAY_NL_DATA_EXTENSION_ID, $payNLCustomData);
    }

    private function addPaynlTransactionStatus(CheckoutFinishPageLoadedEvent $event): void
    {
        $order = $event->getPage()->getOrder();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $order->getId()));
        $criteria->addAssociation('orderTransaction.stateMachineState');

        /** @var PaynlTransactionEntity $paynlTransaction */
        $paynlTransaction = $this->paynlTransactionRepository
            ->search(
                $criteria,
                $event->getSalesChannelContext()->getContext()
            )
            ->first();

        if ($paynlTransaction instanceof PaynlTransactionEntity) {
            $orderTransactionStatus = $paynlTransaction->getOrderTransaction()->getStateMachineState()->getTechnicalName();
            if (in_array($paynlTransaction->getStateId(), PaynlTransactionStatusesEnum::DENIED_STATUSES)) {
                $orderTransactionStatus = 'denied';
            }

            $event->getPage()->assign([
                'PAY' => [
                    'status' => $orderTransactionStatus
                ]
            ]);
        }
    }

    /** @param LineItemCollection|OrderLineItemCollection $lineItems */
    private function calculatePaymentSurcharges(
        PaymentMethodCollection $paymentMethods,
        PaymentMethodEntity $chosenPaymentMethod,
        $lineItems,
        float $totalPrice,
        bool $surchargeApplied,
        SalesChannelContext $context
    ): void {
        $paymentSurcharges = $this->surchargeService->getPaymentMethodsSurcharges(
            array_values($paymentMethods->getIds()),
            $context->getContext()
        );

        $paymentSurcharges->map(function (PaymentSurchargeEntity $paymentSurcharge) use ($lineItems, $context) {
            $priceDefinition = $this->surchargeService->getSurchargePriceDefinition(
                $paymentSurcharge,
                $lineItems->getKeys()
            );
            $price = $this->surchargeService->getCalculatedPrice($priceDefinition, $lineItems->getPrices(), $context);

            $paymentSurcharge->setAmount($price->getTotalPrice());
        });

        $this->surchargeService->applyPaymentMethodSurcharge(
            $chosenPaymentMethod,
            $paymentSurcharges,
            $totalPrice,
            $surchargeApplied
        );

        $this->surchargeService->applyPaymentMethodsSurcharges(
            $paymentSurcharges,
            $paymentMethods,
            $totalPrice,
            $surchargeApplied
        );
    }
}
