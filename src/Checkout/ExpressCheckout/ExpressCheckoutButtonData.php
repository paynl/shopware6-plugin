<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Checkout\ExpressCheckout;

use Shopware\Core\Framework\Struct\Struct;

class ExpressCheckoutButtonData extends Struct
{
    protected bool $expressCheckoutEnabled;
    protected bool $expressShoppingCartEnabled;
    protected string $contextSwitchUrl;
    protected ?string $payPalPaymentMethodId = null;
    protected string $prepareCheckoutUrl;
    protected string $checkoutConfirmUrl;
    protected string $cancelRedirectUrl;
    protected string $clientId;
    protected string $languageIso;
    protected string $currency;
    protected string $intent;
    protected string $buttonShape;
    protected string $buttonColor;
    protected ?string $clientToken = null;
    protected string $paymentMethodId;
    protected string $createOrderUrl;
    protected string $addErrorUrl;
    protected ?string $orderId = null;

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

    public function getIntent(): string
    {
        return $this->intent;
    }

    public function setIntent(string $intent): void
    {
        $this->intent = $intent;
    }

    public function getClientToken(): ?string
    {
        return $this->clientToken;
    }

    public function setClientToken(?string $clientToken): void
    {
        $this->clientToken = $clientToken;
    }

    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }

    public function setPaymentMethodId(string $paymentMethodId): void
    {
        $this->paymentMethodId = $paymentMethodId;
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

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(?string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getButtonShape(): string
    {
        return $this->buttonShape;
    }

    public function setButtonShape(string $buttonShape): void
    {
        $this->buttonShape = $buttonShape;
    }

    public function getButtonColor(): string
    {
        return $this->buttonColor;
    }

    public function setButtonColor(string $buttonColor): void
    {
        $this->buttonColor = $buttonColor;
    }

    public function getContextSwitchUrl(): string
    {
        return $this->contextSwitchUrl;
    }

    public function getPayPalPaymentMethodId(): ?string
    {
        return $this->payPalPaymentMethodId;
    }

    public function getPrepareCheckoutUrl(): string
    {
        return $this->prepareCheckoutUrl;
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
