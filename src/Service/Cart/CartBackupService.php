<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\Cart;

use PaynlPayment\Shopware6\Repository\Order\OrderRepositoryInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartBackupService
{
    private const BACKUP_TOKEN = 'paynl_backup';

    private CartService $cartService;
    private OrderRepositoryInterface $orderRepository;

    public function __construct(CartService $cartService, OrderRepositoryInterface $orderRepository)
    {
        $this->cartService = $cartService;
        $this->orderRepository = $orderRepository;
    }

    public function clearBackup(SalesChannelContext $context): void
    {
        $backupCart = $this->cartService->getCart(self::BACKUP_TOKEN, $context);

        $backupCart->setLineItems(new LineItemCollection());

        $this->cartService->setCart($backupCart);
        $this->cartService->recalculate($backupCart, $context);
    }

    public function restoreCartFromOrder(string $orderId, SalesChannelContext $context): void
    {
        // Fetch the order by ID
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $order = $this->orderRepository->search($criteria, $context->getContext())->first();

        if (!$order) {
            throw new \RuntimeException('Order not found');
        }

        // Get the current cart
        $cart = $this->cartService->getCart($context->getToken(), $context);

        // Remove all items from the cart
        foreach ($cart->getLineItems() as $cartLineItem) {
            $this->cartService->remove($cart, $cartLineItem->getId(), $context);
        }

        // Iterate through order line items and add them to the cart
        foreach ($order->getLineItems() as $orderLineItem) {
            $lineItem = new LineItem(
                uniqid('order_item_', true),
                LineItem::PRODUCT_LINE_ITEM_TYPE,
                $orderLineItem->getReferencedId(),
                $orderLineItem->getQuantity()
            );

            $lineItem->setPayload($orderLineItem->getPayload());

            // Set stackable and removable flags
            $lineItem->setStackable(false); // Ensure the item is stackable
            $lineItem->setRemovable(true); // Allow the item to be removed

            $this->cartService->add($cart, $lineItem, $context);
        }
    }
}
