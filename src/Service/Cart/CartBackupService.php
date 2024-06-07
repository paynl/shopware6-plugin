<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\Cart;

use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartBackupService
{
    private const BACKUP_TOKEN = 'paynl_backup';

    /** @var CartService */
    private $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    public function clearBackup(SalesChannelContext $context): void
    {
        $backupCart = $this->cartService->getCart(self::BACKUP_TOKEN, $context);

        $backupCart->setLineItems(new LineItemCollection());

        $this->cartService->setCart($backupCart);
        $this->cartService->recalculate($backupCart, $context);
    }
}
