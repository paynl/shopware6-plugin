<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PayParts\Storefront;

use Shopware\Core\Framework\Struct\Struct;

class PayPartsCheckoutData extends Struct
{
    public const DEFAULT_COUNTRY_ISO = 'NL';
    public const DEFAULT_LANGUAGE    = 'nl';

    protected string $paymentMethodId    = '';
    protected string $sessionUrl         = '';
    protected string $createOrderUrl     = '';
    protected string $linkTransactionUrl = '';
    protected string $sdkUrl             = '';
    protected string $country            = self::DEFAULT_COUNTRY_ISO;
    protected string $language           = self::DEFAULT_LANGUAGE;

    // Edit-order retry fields — empty on first checkout.
    protected string $orderId            = '';
    protected string $orderTransactionId = '';
    protected string $editOrderUrl       = '';

    public function isEnabled(): bool
    {
        return $this->paymentMethodId !== '';
    }

    public function isEditOrderMode(): bool
    {
        return $this->orderId !== '';
    }

    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }

    public function getSdkUrl(): string
    {
        return $this->sdkUrl;
    }

    /**
     * Options consumed by the PaynlPayPartsCardPlugin storefront script.
     *
     * Edit-order fields are only included when the struct is populated for
     * an existing order (retry flow), keeping the first-checkout payload minimal.
     *
     * @return array<string, string>
     */
    public function getPluginOptions(): array
    {
        $options = [
            'sessionUrl'         => $this->sessionUrl,
            'createOrderUrl'     => $this->createOrderUrl,
            'linkTransactionUrl' => $this->linkTransactionUrl,
            'country'            => $this->country,
            'language'           => $this->language,
        ];

        if ($this->isEditOrderMode()) {
            $options['orderId']            = $this->orderId;
            $options['orderTransactionId'] = $this->orderTransactionId;
            $options['editOrderUrl']       = $this->editOrderUrl;
        }

        return $options;
    }
}

