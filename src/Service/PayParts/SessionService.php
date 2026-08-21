<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PayParts;

use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Exceptions\PayPartsApiException;
use PaynlPayment\Shopware6\Exceptions\PaynlPaymentException;
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
    private const SDK_URL        = 'https://zero-parts.pay.nl/sdk/payparts-sdk.js';

    private PayPartsApiClient $apiClient;
    private SessionDataMapper $dataMapper;
    private Config $config;
    private PaymentUrlBuilder $paymentUrlBuilder;
    private Api $api;

    public function __construct(
        PayPartsApiClient $apiClient,
        SessionDataMapper $dataMapper,
        Config $config,
        PaymentUrlBuilder $paymentUrlBuilder,
        Api $api
    ) {
        $this->apiClient = $apiClient;
        $this->dataMapper = $dataMapper;
        $this->config = $config;
        $this->paymentUrlBuilder = $paymentUrlBuilder;
        $this->api = $api;
    }

    /** @throws PayPartsApiException */
    public function createFromCart(Cart $cart, SalesChannelContext $context): CreateSessionResponse
    {
        $salesChannelId = $context->getSalesChannel()->getId();
        $request        = $this->buildRequestFromCart($cart, $context);

        return $this->executeSessionCreate($request, $salesChannelId);
    }

    /** @throws PayPartsApiException */
    public function createFromOrder(OrderEntity $order, SalesChannelContext $context): CreateSessionResponse
    {
        $salesChannelId = $context->getSalesChannel()->getId();
        $request        = $this->buildRequestFromOrder($order, $context);

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

    private function buildRequestFromCart(Cart $cart, SalesChannelContext $context): CreateSessionRequest
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

    private function buildRequestFromOrder(OrderEntity $order, SalesChannelContext $context): CreateSessionRequest
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

    private function buildOrderFromCart(Cart $cart): SessionOrder
    {
        return new SessionOrder(
            $this->buildProductsFromLineItems($cart->getLineItems()),
            (int) round(($cart->getShippingCosts()?->getTotalPrice() ?? 0) * 100),
            (int) round($cart->getPrice()->getCalculatedTaxes()->getAmount() * 100),
        );
    }

    private function buildOrderFromEntity(OrderEntity $order): SessionOrder
    {
        return new SessionOrder(
            $this->buildProductsFromLineItems($order->getLineItems() ?? []),
            (int) round($order->getShippingTotal() * 100),
            (int) round(($order->getAmountTotal() - $order->getAmountNet()) * 100),
        );
    }

    /** @return SessionProduct[] */
    private function buildProductsFromLineItems(iterable $lineItems): array
    {
        $products = [];

        foreach ($lineItems as $lineItem) {
            if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }

            $price = $lineItem->getPrice();

            $products[] = $this->buildSessionProduct(
                (string) $lineItem->getLabel(),
                $lineItem->getQuantity(),
                (string) $lineItem->getReferencedId(),
                $price?->getUnitPrice() ?? 0.0,
                $price?->getTotalPrice() ?? 0.0,
                $price?->getCalculatedTaxes()->first()?->getTaxRate() ?? 0.0,
            );
        }

        return $products;
    }

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
            address:   $billing ? $this->buildAddress($billing, $context->getSalesChannelId()) : null,
        );
    }

    private function buildAddress(CustomerAddressEntity $address, string $salesChannelId): SessionAddress
    {
        [$street, $houseNumber] = $this->parseStreetAndHouseNumber($address, $salesChannelId);

        return new SessionAddress(
            street:      $street,
            houseNumber: $houseNumber,
            postalCode:  $address->getZipcode() ?? '',
            city:        $address->getCity() ?? '',
            country:     $address->getCountry()?->getIso() ?? '',
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseStreetAndHouseNumber(CustomerAddressEntity $address, string $salesChannelId): array
    {
        $street = $address->getStreet() ?? '';
        $houseNumber = '';
        $houseNumberExtension = '';

        if (!$this->config->getUseAdditionalAddressFields($salesChannelId)) {
            $parsed = paynl_split_address($street);
            $street = $parsed['street'] ?? '';
            $houseNumber = $parsed['number'] ?? '';

            $houseNumberParts = explode(' ', (string) $houseNumber);
            if (count($houseNumberParts) > 1) {
                $houseNumber = array_shift($houseNumberParts);
                $houseNumberExtension = implode(' ', $houseNumberParts);
            }
        } else {
            $houseNumber = $address->getAdditionalAddressLine1() ?? '';
            $houseNumberExtension = $address->getAdditionalAddressLine2() ?? '';
        }

        if ($houseNumberExtension !== '') {
            $houseNumber = trim($houseNumber . ' ' . $houseNumberExtension);
        }

        return [$street, $houseNumber];
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

    /** @throws PayPartsApiException */
    private function buildBasicToken(string $salesChannelId): string
    {
        $slCode = trim($this->config->getServiceId($salesChannelId));
        if ($slCode === '') {
            throw PayPartsApiException::sessionCreateFailed('Service ID is not configured');
        }

        try {
            $secret = $this->api->getServiceSecret($salesChannelId);
        } catch (PaynlPaymentException $exception) {
            throw PayPartsApiException::sessionCreateFailed($exception->getMessage());
        }

        return base64_encode($slCode . ':' . $secret);
    }
}
