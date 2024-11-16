<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Checkout\ExpressCheckout;

use Shopware\Core\Framework\Struct\Struct;

class IdealExpressCheckoutButtonData extends Struct
{
    protected bool $expressCheckoutEnabled;
    protected bool $expressShoppingCartEnabled;

    /**
     * @return bool
     */
    public function isExpressCheckoutEnabled(): bool
    {
        return $this->expressCheckoutEnabled;
    }

    /**
     * @param bool $expressCheckoutEnabled
     */
    public function setExpressCheckoutEnabled(bool $expressCheckoutEnabled): void
    {
        $this->expressCheckoutEnabled = $expressCheckoutEnabled;
    }

    public function isExpressShoppingCartEnabled(): bool
    {
        return $this->expressShoppingCartEnabled;
    }

    public function setExpressShoppingCartEnabled(bool $expressShoppingCartEnabled): void
    {
        $this->expressShoppingCartEnabled = $expressShoppingCartEnabled;
    }
}
