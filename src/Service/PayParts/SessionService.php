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
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SessionService
{
    private const PRODUCTION_URL = 'https://parts.pay.nl';
    private const BETA_URL       = 'https://zero-parts.pay.nl';
    private const SDK_URL        = 'https://parts.pay.nl/sdk/payparts-sdk.js';

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
        $request        = $this->buildRequestFromCart($cart, $context, $salesChannelId);

        return $this->executeSessionCreate($request, $salesChannelId);
    }

    /** @throws PayPartsApiException */
    public function createFromOrder(OrderEntity $order, SalesChannelContext $context): CreateSessionResponse
    {
        $salesChannelId = $context->getSalesChannel()->getId();
        $request        = $this->buildRequestFromOrder($order, $context, $salesChannelId);

        return $this->executeSessionCreate($request, $salesChannelId);
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

    public function getApiUrl(string $salesChannelId): string
    {
        return $this->config->getTestMode($salesChannelId)
            ? self::BETA_URL
            : self::PRODUCTION_URL;
    }

    public function getSdkUrl(): string
    {
        return self::SDK_URL;
    }

    // ─── Request builders ────────────────────────────────────────────────────

    private function buildRequestFromCart(Cart $cart, SalesChannelContext $context, string $salesChannelId): CreateSessionRequest
    {
        $amountCents = (int)round($cart->getPrice()->getTotalPrice() * 100);
        $currency    = $context->getCurrency()->getIsoCode();
        $customer    = $context->getCustomer();

        return new CreateSessionRequest(
            returnUrl:   $this->paymentUrlBuilder->buildReturnUrl(''),
            exchangeUrl: $this->paymentUrlBuilder->buildExchangeUrl(),
            amount:      new SessionAmount($amountCents, $currency),
            customer:    $customer ? $this->buildCustomer($customer, $context) : null,
            order:       $this->buildOrderFromCart($cart),
        );
    }

    private function buildRequestFromOrder(OrderEntity $order, SalesChannelContext $context, string $salesChannelId): CreateSessionRequest
    {
        $amountCents = (int)round($order->getAmountTotal() * 100);
        // Order currency takes precedence; fall back to context when not yet associated.
        $currency    = $order->getCurrency()?->getIsoCode() ?? $context->getCurrency()->getIsoCode();
        $customer    = $context->getCustomer();

        return new CreateSessionRequest(
            returnUrl:   $this->paymentUrlBuilder->buildReturnUrl(''),
            exchangeUrl: $this->paymentUrlBuilder->buildExchangeUrl(),
            amount:      new SessionAmount($amountCents, $currency),
            reference:   $order->getOrderNumber(),
            customer:    $customer ? $this->buildCustomer($customer, $context) : null,
            order:       $this->buildOrderFromEntity($order),
        );
    }

    // ─── Order builders ──────────────────────────────────────────────────────

    private function buildOrderFromCart(Cart $cart): SessionOrder
    {
        $products      = [];
        $totalVatCents = 0;

        foreach ($cart->getLineItems() as $lineItem) {
            if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }

            $price          = $lineItem->getPrice();
            $vatCents       = (int)round(($price?->getCalculatedTaxes()->getAmount() ?? 0) * 100);
            $totalVatCents += $vatCents;

            $products[] = $this->buildSessionProduct(
                (string)$lineItem->getLabel(),
                $lineItem->getQuantity(),
                (string)$lineItem->getReferencedId(),
                $price?->getUnitPrice() ?? 0.0,
                $price?->getTotalPrice() ?? 0.0,
                $price?->getCalculatedTaxes()->first()?->getTaxRate() ?? 0.0,
            );
        }

        $shippingCents = (int)round(($cart->getShippingCosts()?->getTotalPrice() ?? 0) * 100);

        return new SessionOrder($products, $shippingCents, $totalVatCents);
    }

    private function buildOrderFromEntity(OrderEntity $order): SessionOrder
    {
        $products      = [];
        $totalVatCents = 0;

        foreach ($order->getLineItems() ?? [] as $lineItem) {
            if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }

            $price          = $lineItem->getPrice();
            $vatCents       = (int)round(($price?->getCalculatedTaxes()->getAmount() ?? 0) * 100);
            $totalVatCents += $vatCents;

            $products[] = $this->buildSessionProduct(
                (string)$lineItem->getLabel(),
                $lineItem->getQuantity(),
                (string)$lineItem->getReferencedId(),
                $price?->getUnitPrice() ?? 0.0,
                $price?->getTotalPrice() ?? 0.0,
                $price?->getCalculatedTaxes()->first()?->getTaxRate() ?? 0.0,
            );
        }

        $shippingCents = (int)round($order->getShippingTotal() * 100);

        return new SessionOrder($products, $shippingCents, $totalVatCents);
    }

    // ─── Shared helpers ──────────────────────────────────────────────────────

    /** Single source of truth for converting line-item price fields into a SessionProduct. */
    private function buildSessionProduct(
        string $label,
        int $quantity,
        string $productId,
        float $unitPrice,
        float $totalPrice,
        float $vatRate
    ): SessionProduct {
        return new SessionProduct(
            description:   $label,
            quantity:      $quantity,
            unitPrice:     (int)round($unitPrice * 100),
            total:         (int)round($totalPrice * 100),
            productId:     $productId,
            vatPercentage: $vatRate,
        );
    }

    private function buildCustomer(CustomerEntity $customer, SalesChannelContext $context): SessionCustomer
    {
        $billing = $customer->getActiveBillingAddress() ?? $customer->getDefaultBillingAddress();
        // Extract the BCP 47 language subtag from "nl-NL", "de-DE", etc.
        $localeCode = $context->getLanguageInfo()->localeCode ?: 'en-GB';
        $language   = strtolower(substr($localeCode, 0, 2));

        return new SessionCustomer(
            locale:    $language,
            firstName: $customer->getFirstName(),
            lastName:  $customer->getLastName(),
            email:     $customer->getEmail(),
            phone:     $billing?->getPhoneNumber(),
            address:   $billing ? $this->buildAddress($billing) : null,
        );
    }

    private function buildAddress(CustomerAddressEntity $address): SessionAddress
    {
        return new SessionAddress(
            street:      $address->getStreet() ?? '',
            houseNumber: $address->getAdditionalAddressLine1() ?? '',
            postalCode:  $address->getZipcode() ?? '',
            city:        $address->getCity() ?? '',
            country:     $address->getCountry()?->getIso() ?? '',
        );
    }

    /** @throws PayPartsApiException */
    private function executeSessionCreate(CreateSessionRequest $request, string $salesChannelId): CreateSessionResponse
    {
        $rawResponse = $this->apiClient->createSession(
            $request->toArray(),
            $this->getApiUrl($salesChannelId),
            $this->buildBasicToken($salesChannelId)
        );

        return $this->dataMapper->mapCreateSession($rawResponse);
    }

    private function buildBasicToken(string $salesChannelId): string
    {
        $slCode = $this->config->getServiceId($salesChannelId);
        $secret = $this->config->getApiToken($salesChannelId);

        return base64_encode($slCode . ':' . $secret);
    }
}
