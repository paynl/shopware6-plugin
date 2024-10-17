<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Checkout\ExpressCheckout\Service;

use PaynlPayment\Shopware6\Checkout\ExpressCheckout\ExpressCheckoutButtonData;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface ExpressCheckoutDataServiceInterface
{
    public function buildExpressCheckoutButtonData(SalesChannelContext $salesChannelContext, bool $addProductToCart = false): ?ExpressCheckoutButtonData;
}
