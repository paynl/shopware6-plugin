<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\Cart;

use Shopware\Core\Checkout\Cart\Cart;
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

    public function restoreCart(SalesChannelContext $context): Cart
    {
        $backupCart = $this->cartService->getCart(self::BACKUP_TOKEN, $context);

        $newCart = $this->cartService->createNew($context->getToken());
        $newCart->setLineItems($backupCart->getLineItems());

        $this->cartService->setCart($newCart);
        $newCart = $this->cartService->recalculate($newCart, $context);

        return $newCart;
    }

    public function isBackupExisting(SalesChannelContext $context): bool
    {
        $backupCart = $this->cartService->getCart(self::BACKUP_TOKEN, $context);

        return ($backupCart->getLineItems()->count() > 0);
    }

    public function backupCart(SalesChannelContext $context): void
    {
        $originalCart = $this->cartService->getCart($context->getToken(), $context);

        $newCart = $this->cartService->createNew(self::BACKUP_TOKEN);

        $newCart->setLineItems($originalCart->getLineItems());

        $this->cartService->setCart($newCart);
        $this->cartService->recalculate($newCart, $context);
    }
}
