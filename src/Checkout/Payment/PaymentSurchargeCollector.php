<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Checkout\Payment;

use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Exceptions\PriceDefinitionInstance;
use PaynlPayment\Shopware6\Helper\MediaHelper;
use PaynlPayment\Shopware6\Service\PaymentMethod\PaymentMethodSurchargeService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\LineItemFactoryInterface;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Checkout\Promotion\Cart\PromotionCollector;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;

class PaymentSurchargeCollector implements CartDataCollectorInterface, CartProcessorInterface
{
    /** @var Config */
    protected $config;

    /** @var PaymentMethodSurchargeService */
    protected $surchargeService;

    /** @var AbsolutePriceCalculator */
    protected $absolutePriceCalculator;

    /** @var LineItemFactoryInterface */
    protected $lineItemFactory;

    /** @var PercentagePriceCalculator */
    protected $percentagePriceCalculator;
    /** @var MediaHelper */
    protected $mediaHelper;

    public function __construct(
        Config $config,
        PaymentMethodSurchargeService $surchargeService,
        LineItemFactoryInterface $lineItemFactory,
        AbsolutePriceCalculator $absolutePriceCalculator,
        PercentagePriceCalculator $percentagePriceCalculator,
        MediaHelper $mediaHelper
    ) {
        $this->config = $config;
        $this->surchargeService = $surchargeService;
        $this->absolutePriceCalculator = $absolutePriceCalculator;
        $this->lineItemFactory = $lineItemFactory;
        $this->percentagePriceCalculator = $percentagePriceCalculator;
        $this->mediaHelper = $mediaHelper;
    }

    public function collect(
        CartDataCollection $data,
        Cart $original,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        $surcharge = $this->surchargeService->getPaymentMethodSurcharge(
            $context->getPaymentMethod(),
            $context->getContext()
        );
        $totalPrice = $original->getPrice()->getTotalPrice();

        if (!$surcharge
            || $surcharge->getAmount() <= 0
            || ($surcharge->getOrderValueLimit() > 0 && $totalPrice >= $surcharge->getOrderValueLimit())
        ) {
            return;
        }

        $data->set('paynl_payment_surcharge', $surcharge);
    }

    /** @throws PriceDefinitionInstance */
    public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        SalesChannelContext $salesChannelContext,
        CartBehavior $behavior
    ): void {
        $paymentMethod = $salesChannelContext->getPaymentMethod();
        $surcharge = $data->get('paynl_payment_surcharge');
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();

        $skipPromotion = $salesChannelContext->hasPermission(PromotionCollector::SKIP_PROMOTION);
        $isActivePluginSurcharge = $this->config->isSurchargePaymentMethods($salesChannelId);
        $hasLineItems = $toCalculate->getLineItems()->count() > 0;
        $hasPaymentMethodSurcharge = $toCalculate->getLineItems()
                ->filterType(PaymentMethodSurchargeService::LINE_ITEM_TYPE)
                ->count() > 0;
        $hasPaymentMethod = $toCalculate->has($paymentMethod->getId());

        if (!$surcharge || $skipPromotion || !$isActivePluginSurcharge || !$hasLineItems
            || $hasPaymentMethodSurcharge || $hasPaymentMethod
        ) {
            return;
        }

        $surchargeLineItem = $this->createSurchargeLineItem(
            $salesChannelContext->getPaymentMethod(),
            $salesChannelContext
        );

        $definition = $this->surchargeService->getSurchargePriceDefinition(
            $surcharge,
            $toCalculate->getLineItems()->getKeys()
        );

        $surchargeLineItem->setPriceDefinition($definition);

        $surchargeLineItem->setPrice(
            $this->surchargeService->getCalculatedPrice(
                $definition,
                $toCalculate->getLineItems()->getPrices(),
                $salesChannelContext
            )
        );

        $toCalculate->add($surchargeLineItem);
    }

    private function createSurchargeLineItem(
        PaymentMethodEntity $paymentMethod,
        SalesChannelContext $salesChannelContext
    ): LineItem {
        $salesChannelPermissions = $salesChannelContext->getPermissions();
        $updatePermissions = false;
        if (empty($salesChannelPermissions)) {
            $updatePermissions = true;
            $salesChannelContext->setPermissions($this->getSalesChannelPermissions($salesChannelContext));
        }

        $lineItem = $this->lineItemFactory->create([
            'id' => $paymentMethod->getId(),
            'type' => PaymentMethodSurchargeService::LINE_ITEM_TYPE,
            'label' => $paymentMethod->getTranslation('name') ?: 'Payment Surcharge',
            'stackable' => true,
            'removable' => false,
            'good' => false,
            'payload' => [
                'customFields' => []
            ]
        ], $salesChannelContext);

        $lineItem->setGood(false);

        $shoppingBasketIconMedia = $this->mediaHelper->getMedia(
            MediaHelper::SHOPPING_BASKET_ICON,
            $salesChannelContext->getContext()
        );
        if ($shoppingBasketIconMedia) {
            $lineItem->setCover($shoppingBasketIconMedia);
        }

        if ($updatePermissions) {
            $salesChannelContext->setPermissions($salesChannelPermissions);
        }

        return $lineItem;
    }

    private function getSalesChannelPermissions(SalesChannelContext $salesChannelContext): array
    {
        $salesChannelPermissions = $salesChannelContext->getPermissions();
        $salesChannelPermissions[ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES] = true;

        return $salesChannelPermissions;
    }
}
