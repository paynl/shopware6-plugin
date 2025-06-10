<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface CartServiceInterface
{
    public function updatePaymentMethod(SalesChannelContext $context, string $paymentMethodID): SalesChannelContext;
    public function getCalculatedMainCart(SalesChannelContext $salesChannelContext): Cart;
    public function updateCart(Cart $cart): void;
    public function addProduct(string $productId, int $quantity, SalesChannelContext $context): Cart;
}
