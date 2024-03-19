<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PaymentMethod;

use PaynlPayment\Shopware6\Entity\PaymentSurchargeEntity;
use PaynlPayment\Shopware6\Exceptions\PriceDefinitionInstance;
use PaynlPayment\Shopware6\Repository\Media\MediaRepositoryInterface;
use PaynlPayment\Shopware6\Repository\Order\OrderRepositoryInterface;
use PaynlPayment\Shopware6\Repository\OrderLineItem\OrderLineItemRepositoryInterface;
use PaynlPayment\Shopware6\Repository\PaynlPaymentSurcharge\PaynlPaymentSurchargeRepositoryInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\LineItemRule;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;

class PaymentMethodSurchargeService
{
    public const PAYMENT_SURCHARGE_EXTENSION = 'PaynlPaymentSurcharge';
    public const LINE_ITEM_TYPE = 'payment_surcharge';

    /** @var PaynlPaymentSurchargeRepositoryInterface */
    protected $paymentSurchargeRepository;
    /** @var OrderLineItemRepositoryInterface */
    protected $orderLineItemRepository;
    /** @var OrderRepositoryInterface */
    protected $orderRepository;
    /** @var MediaRepositoryInterface */
    protected $mediaRepository;
    /** @var OrderConverter */
    protected $orderConverter;
    /** @var Processor */
    protected $cartProcessor;
    /** @var PercentagePriceCalculator */
    protected $percentagePriceCalculator;
    /** @var AbsolutePriceCalculator */
    protected $absolutePriceCalculator;

    public function __construct(
        PaynlPaymentSurchargeRepositoryInterface $paymentSurchargeRepository,
        OrderLineItemRepositoryInterface $orderLineItemRepository,
        OrderRepositoryInterface $orderRepository,
        MediaRepositoryInterface $mediaRepository,
        OrderConverter $orderConverter,
        Processor $cartProcessor,
        PercentagePriceCalculator $percentagePriceCalculator,
        AbsolutePriceCalculator $absolutePriceCalculator
    ) {
        $this->paymentSurchargeRepository = $paymentSurchargeRepository;
        $this->orderLineItemRepository = $orderLineItemRepository;
        $this->orderRepository = $orderRepository;
        $this->mediaRepository = $mediaRepository;
        $this->orderConverter = $orderConverter;
        $this->cartProcessor = $cartProcessor;
        $this->percentagePriceCalculator = $percentagePriceCalculator;
        $this->absolutePriceCalculator = $absolutePriceCalculator;
    }

    public function applyPaymentMethodsSurcharges(
        EntityCollection $paymentSurcharges,
        EntityCollection &$paymentMethods,
        float $totalPrice,
        bool $isSurchargeApplied
    ): EntityCollection {
        /** @var PaymentSurchargeEntity $surcharge */
        foreach ($paymentSurcharges as $surcharge) {
            $paymentMethod = $paymentMethods->get($surcharge->getPaymentMethodId());
            if (!$paymentMethod) {
                continue;
            }

            $grandTotal = $isSurchargeApplied ? $totalPrice - $surcharge->getAmount() : $totalPrice;

            if ($surcharge->getOrderValueLimit() <= 0 || $grandTotal < $surcharge->getOrderValueLimit()) {
                $paymentMethod->addExtension(
                    self::PAYMENT_SURCHARGE_EXTENSION,
                    new ArrayStruct(['surcharge_amount' => $surcharge->getAmount()])
                );

                continue;
            }

            $paymentMethod->removeExtension(self::PAYMENT_SURCHARGE_EXTENSION);
        }

        return $paymentMethods;
    }

    public function applyPaymentMethodSurcharge(
        PaymentMethodEntity $chosenPaymentMethod,
        EntityCollection $surcharges,
        float $totalPrice,
        bool $isSurchargeApplied
    ): PaymentMethodEntity {
        /** @var PaymentSurchargeEntity|null $surcharge */
        $surcharge = $surcharges->get($chosenPaymentMethod->getId());
        $grandTotal = $isSurchargeApplied && $surcharge !== null
            ? $totalPrice - $surcharge->getAmount()
            : $totalPrice;

        if ($surcharge && !($surcharge->getOrderValueLimit() > 0 && $grandTotal >= $surcharge->getOrderValueLimit())) {
            $chosenPaymentMethod->addExtension(
                self::PAYMENT_SURCHARGE_EXTENSION,
                new ArrayStruct(['surcharge_amount' => $surcharge->getAmount()])
            );

            return $chosenPaymentMethod;
        }

        $chosenPaymentMethod->removeExtension(self::PAYMENT_SURCHARGE_EXTENSION);

        return $chosenPaymentMethod;
    }

    public function getPaymentMethodSurcharge(
        PaymentMethodEntity $paymentMethodEntity,
        Context $context
    ): ?PaymentSurchargeEntity {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paymentMethodId', $paymentMethodEntity->getId()));

        return $this->paymentSurchargeRepository->search($criteria, $context)->first();
    }

    public function getPaymentMethodsSurcharges(array $paymentMethodIds, Context $context): EntityCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('paymentMethodId', $paymentMethodIds));

        return $this->paymentSurchargeRepository->search($criteria, $context)->getEntities();
    }

    public function updateOrderTotal(
        OrderEntity $order,
        SalesChannelContext $salesChannelContext,
        bool $keepPaymentSurchargeLineItems = false
    ): OrderEntity {
        $context = $salesChannelContext->getContext();
        $context->setRuleIds($order->getRuleIds());

        $cart = $this->orderConverter->convertToCart($order, $salesChannelContext->getContext());

        $permissions = $this->getRecalculatePaymentSurchargePermissions($salesChannelContext);

        $processedCart = $this->cartProcessor->process($cart, $salesChannelContext, new CartBehavior($permissions));

        $orderConversionContext = (new OrderConversionContext())
            ->setIncludeCustomer(true)
            ->setIncludeBillingAddress(true)
            ->setIncludeDeliveries(true)
            ->setIncludeTransactions(false);

        $orderData = $this->orderConverter->convertToOrder(
            $processedCart,
            $salesChannelContext,
            $orderConversionContext
        );

        if (!$keepPaymentSurchargeLineItems) {
            $this->deletePaymentSurchargeLineItems($order, $orderData, $context);
        }

        $order->setPrice($orderData['price']);

        $this->buildOrderLineItems($order, $orderData['lineItems'], $context);

        return $order;
    }

    public function getSurchargePriceDefinition(
        PaymentSurchargeEntity $paymentSurcharge,
        array $lineItemKeys
    ): PriceDefinitionInterface {
        if ($paymentSurcharge->getType() === PaymentSurchargeEntity::TYPE_PERCENTAGE) {
            return new PercentagePriceDefinition(
                $paymentSurcharge->getAmount(),
                new LineItemRule(LineItemRule::OPERATOR_EQ, $lineItemKeys)
            );
        }

        return new AbsolutePriceDefinition(
            $paymentSurcharge->getAmount(),
            new LineItemRule(LineItemRule::OPERATOR_EQ, $lineItemKeys)
        );
    }

    /** @throws PriceDefinitionInstance */
    public function getCalculatedPrice(
        PriceDefinitionInterface $priceDefinition,
        PriceCollection $priceCollection,
        SalesChannelContext $salesChannelContext
    ): CalculatedPrice {
        if ($priceDefinition instanceof AbsolutePriceDefinition) {
            return $this->absolutePriceCalculator->calculate(
                $priceDefinition->getPrice(),
                $priceCollection,
                $salesChannelContext
            );
        }

        if ($priceDefinition instanceof PercentagePriceDefinition) {
            return $this->percentagePriceCalculator->calculate(
                $priceDefinition->getPercentage(),
                $priceCollection,
                $salesChannelContext
            );
        }

        throw PriceDefinitionInstance::unknownPriceDefinitionProvided();
    }

    private function buildOrderLineItems(OrderEntity $order, array $orderLineItems, Context $context): void
    {
        $order->setLineItems(new OrderLineItemCollection(array_map(function ($lineItemData) use ($context) {
            $lineItem = new OrderLineItemEntity();
            $lineItem->setId($lineItemData['id']);
            $lineItem->setIdentifier($lineItemData['identifier']);
            $lineItem->setProductId($lineItemData['productId'] ?? null);
            $lineItem->setReferencedId($lineItemData['referencedId'] ?? null);
            $lineItem->setQuantity($lineItemData['quantity']);
            $lineItem->setType($lineItemData['type']);
            $lineItem->setLabel($lineItemData['label']);
            $lineItem->setGood($lineItemData['good']);
            $lineItem->setRemovable($lineItemData['removable']);
            $lineItem->setStackable($lineItemData['stackable']);
            $lineItem->setPosition($lineItemData['position']);
            $lineItem->setPrice($lineItemData['price']);
            $lineItem->setPayload($lineItemData['payload']);

            if (isset($lineItemData['coverId'])) {
                $lineItem->setCoverId('coverId');
                $cover = $this->mediaRepository->search(new Criteria([$lineItemData['coverId']]), $context)->first();
                $lineItem->setCover($cover);
            }

            if (isset($lineItemData['parentId'])) {
                $lineItem->setParentId($lineItemData['parentId']);
            }

            return $lineItem;
        }, $orderLineItems)));
    }

    private function deletePaymentSurchargeLineItems(OrderEntity $order, array $orderData, Context $context)
    {
        $lineItemIds = array_values($order->getLineItems()
            ->filterByType(self::LINE_ITEM_TYPE)
            ->getIds()
        );
        if ($lineItemIds) {
            $this->orderLineItemRepository->delete(
                array_map(function ($id) { return ['id' => $id]; }, $lineItemIds),
                $context
            );
        }

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($orderData): void {
            unset($orderData['deliveries']);

            $lineItems = array_filter($orderData['lineItems'], function ($orderItem) {
                return $orderItem['type'] !== LineItem::PROMOTION_LINE_ITEM_TYPE;
            });

            $this->orderRepository->upsert([[
                'id' => $orderData['id'],
                'lineItems' => $lineItems,
                'price' => $orderData['price'],
            ]], $context);
        });
    }

    private function getRecalculatePaymentSurchargePermissions(SalesChannelContext $salesChannelContext): array
    {
        $permissions = $salesChannelContext->getPermissions();
        $permissions[ProductCartProcessor::SKIP_PRODUCT_STOCK_VALIDATION] = true;
        $permissions[ProductCartProcessor::KEEP_INACTIVE_PRODUCT] = true;

        return $permissions;
    }
}
