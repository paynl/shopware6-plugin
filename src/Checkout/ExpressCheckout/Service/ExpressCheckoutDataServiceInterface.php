<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Checkout\ExpressCheckout\Service;

use PaynlPayment\Shopware6\Checkout\ExpressCheckout\PayPalExpressCheckoutButtonData;
use PaynlPayment\Shopware6\Checkout\ExpressCheckout\IdealExpressCheckoutButtonData;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface ExpressCheckoutDataServiceInterface
{
    public function buildPayPalExpressCheckoutButtonData(SalesChannelContext $salesChannelContext, bool $addProductToCart = false): ?PayPalExpressCheckoutButtonData;
    public function buildIdealExpressCheckoutButtonData(SalesChannelContext $salesChannelContext, bool $addProductToCart = false): ?IdealExpressCheckoutButtonData;
}
