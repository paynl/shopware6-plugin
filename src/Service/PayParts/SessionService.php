<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PayParts;

use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Exceptions\PayPartsApiException;
use PaynlPayment\Shopware6\Service\Router\PaymentUrlBuilder;
use PaynlPayment\Shopware6\ValueObjects\PayParts\Request\CreateSessionRequest;
use PaynlPayment\Shopware6\ValueObjects\PayParts\Request\SessionAddress;
use PaynlPayment\Shopware6\ValueObjects\PayParts\Request\SessionAmount;
use PaynlPayment\Shopware6\ValueObjects\PayParts\Request\SessionCustomer;
use PaynlPayment\Shopware6\ValueObjects\PayParts\Request\SessionOrder;
use PaynlPayment\Shopware6\ValueObjects\PayParts\Request\SessionProduct;
use PaynlPayment\Shopware6\ValueObjects\PayParts\Response\CreateSessionResponse;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SessionService
{
    private const PRODUCTION_URL = 'https://parts.pay.nl';
    private const BETA_URL       = 'https://zero-parts.pay.nl';

    private PayPartsApiClient $apiClient;
    private SessionDataMapper $dataMapper;
    private Config $config;
    private PaymentUrlBuilder $paymentUrlBuilder;

    public function __construct(
        PayPartsApiClient $apiClient,
        SessionDataMapper $dataMapper,
        Config $config,
        PaymentUrlBuilder $paymentUrlBuilder
    ) {
        $this->apiClient = $apiClient;
        $this->dataMapper = $dataMapper;
        $this->config = $config;
        $this->paymentUrlBuilder = $paymentUrlBuilder;
    }

    /** @throws PayPartsApiException */
    public function createFromCart(Cart $cart, SalesChannelContext $context): CreateSessionResponse
    {
        $salesChannelId = $context->getSalesChannel()->getId();
        $request = $this->buildRequest($cart, $context, $salesChannelId);
        $rawResponse = $this->apiClient->createSession(
            $request->toArray(),
            $this->getApiUrl($salesChannelId),
            $this->buildBasicToken($salesChannelId)
        );

        return $this->dataMapper->mapCreateSession($rawResponse);
    }

    /** @throws PayPartsApiException */
    public function getSession(string $sessionId, string $salesChannelId): array
    {
        return $this->apiClient->getSession(
            $sessionId,
            $this->getApiUrl($salesChannelId),
            $this->buildBasicToken($salesChannelId)
        );
    }

    private function buildRequest(Cart $cart, SalesChannelContext $context, string $salesChannelId): CreateSessionRequest
    {
        $amountCents = (int)round($cart->getPrice()->getTotalPrice() * 100);
        $currency = $context->getCurrency()->getIsoCode();
        $customer = $context->getCustomer();

        return new CreateSessionRequest(
            returnUrl: $this->paymentUrlBuilder->buildReturnUrl(''),
            exchangeUrl: $this->paymentUrlBuilder->buildExchangeUrl(),
            amount: new SessionAmount($amountCents, $currency),
            reference: $context->getToken(), // cart token; replaced by order number in onSubmit
            customer: $customer ? $this->buildCustomer($customer, $context) : null,
            order: $this->buildOrder($cart)
        );
    }

    private function buildCustomer(
        CustomerEntity $customer,
        SalesChannelContext $context
    ): SessionCustomer {
        $billing = $customer->getActiveBillingAddress() ?? $customer->getDefaultBillingAddress();
        // Extract the BCP 47 language subtag from "nl-NL", "de-DE", etc.
        // Falls back to "en" if the locale association is not loaded.
        $localeCode = $context->getLanguage()->getTranslationCode()?->getCode() ?? 'en-GB';
        $language   = strtolower(substr($localeCode, 0, 2));

        return new SessionCustomer(
            locale: $language,
            firstName: $customer->getFirstName(),
            lastName: $customer->getLastName(),
            email: $customer->getEmail(),
            phone: $billing?->getPhoneNumber(),
            address: $billing ? $this->buildAddress($billing) : null
        );
    }

    private function buildAddress(
        CustomerAddressEntity $address
    ): SessionAddress {
        return new SessionAddress(
            street: $address->getStreet() ?? '',
            houseNumber: $address->getAdditionalAddressLine1() ?? '',
            postalCode: $address->getZipcode() ?? '',
            city: $address->getCity() ?? '',
            country: $address->getCountry()?->getIso() ?? ''
        );
    }

    private function buildOrder(Cart $cart): SessionOrder
    {
        $products = [];
        $totalVatCents = 0;

        foreach ($cart->getLineItems() as $lineItem) {
            if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }

            $price = $lineItem->getPrice();
            $unitCents = (int)round(($price?->getUnitPrice() ?? 0) * 100);
            $totalCents = (int)round(($price?->getTotalPrice() ?? 0) * 100);
            $vatRate = $price?->getCalculatedTaxes()->first()?->getTaxRate() ?? 0.0;
            $vatCents = (int)round(($price?->getCalculatedTaxes()->getAmount() ?? 0) * 100);

            $totalVatCents += $vatCents;

            $products[] = new SessionProduct(
                description: (string)$lineItem->getLabel(),
                quantity: $lineItem->getQuantity(),
                unitPrice: $unitCents,
                total: $totalCents,
                productId: (string)$lineItem->getReferencedId(),
                vatPercentage: $vatRate
            );
        }

        $shippingCents = (int)round(
            ($cart->getShippingCosts()?->getTotalPrice() ?? 0) * 100
        );

        return new SessionOrder($products, $shippingCents, $totalVatCents);
    }

    public function getApiUrl(string $salesChannelId): string
    {
        return $this->config->getTestMode($salesChannelId)
            ? self::BETA_URL
            : self::PRODUCTION_URL;
    }

    private function buildBasicToken(string $salesChannelId): string
    {
        $slCode = $this->config->getServiceId($salesChannelId);
        $secret = $this->config->getApiToken($salesChannelId);

        return base64_encode($slCode . ':' . $secret);
    }

}