<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Checkout\ExpressCheckout;

use Shopware\Core\Framework\Struct\Struct;

class PayPalExpressCheckoutButtonData extends Struct
{
    protected bool $expressCheckoutEnabled;
    protected bool $expressShoppingCartEnabled;
    protected bool $expressProductPageEnabled;
    protected string $contextSwitchUrl;
    protected bool $addProductToCart;
    protected ?string $payPalPaymentMethodId = null;
    protected string $checkoutConfirmUrl;
    protected string $cancelRedirectUrl;
    protected string $clientId;
    protected string $languageIso;
    protected string $currency;
    protected string $createOrderUrl;
    protected string $addErrorUrl;

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

    public function isExpressProductPageEnabled(): bool
    {
        return $this->expressProductPageEnabled;
    }

    public function setExpressProductPageEnabled(bool $expressProductPageEnabled): void
    {
        $this->expressProductPageEnabled = $expressProductPageEnabled;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function setClientId(string $clientId): void
    {
        $this->clientId = $clientId;
    }

    public function getLanguageIso(): string
    {
        return $this->languageIso;
    }

    public function setLanguageIso(string $languageIso): void
    {
        $this->languageIso = $languageIso;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function getCreateOrderUrl(): string
    {
        return $this->createOrderUrl;
    }

    public function setCreateOrderUrl(string $createOrderUrl): void
    {
        $this->createOrderUrl = $createOrderUrl;
    }

    public function getAddErrorUrl(): string
    {
        return $this->addErrorUrl;
    }

    public function setAddErrorUrl(string $addErrorUrl): void
    {
        $this->addErrorUrl = $addErrorUrl;
    }

    public function getContextSwitchUrl(): string
    {
        return $this->contextSwitchUrl;
    }

    public function getPayPalPaymentMethodId(): ?string
    {
        return $this->payPalPaymentMethodId;
    }

    public function getAddProductToCart(): bool
    {
        return $this->addProductToCart;
    }

    public function getCheckoutConfirmUrl(): string
    {
        return $this->checkoutConfirmUrl;
    }

    public function getCancelRedirectUrl(): string
    {
        return $this->cancelRedirectUrl;
    }
}
