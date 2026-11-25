<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PayParts;

use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Exceptions\PayPartsApiException;
use PaynlPayment\Shopware6\Exceptions\PaynlPaymentException;
use PaynlPayment\Shopware6\Helper\LocaleCodeHelper;
use PaynlPayment\Shopware6\Service\Router\PaymentUrlBuilder;
use PaynlPayment\Shopware6\ValueObjects\PayParts\Request\CreateSessionRequest;
use PaynlPayment\Shopware6\ValueObjects\PayParts\Request\SessionAddress;
use PaynlPayment\Shopware6\ValueObjects\PayParts\Request\SessionAmount;
use PaynlPayment\Shopware6\ValueObjects\PayParts\Request\SessionCustomer;
use PaynlPayment\Shopware6\ValueObjects\PayParts\Response\CreateSessionResponse;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SessionService
{
    private const PRODUCTION_URL = 'https://parts.pay.nl';
    private const SDK_URL = 'https://parts.pay.nl/sdk/payparts-sdk.js';

    private PayPartsApiClient $apiClient;
    private SessionDataMapper $dataMapper;
    private Config $config;
    private PaymentUrlBuilder $paymentUrlBuilder;
    private Api $api;
    private LocaleCodeHelper $localeCodeHelper;

    public function __construct(
        PayPartsApiClient $apiClient,
        SessionDataMapper $dataMapper,
        Config $config,
        PaymentUrlBuilder $paymentUrlBuilder,
        Api $api,
        LocaleCodeHelper $localeCodeHelper
    ) {
        $this->apiClient = $apiClient;
        $this->dataMapper = $dataMapper;
        $this->config = $config;
        $this->paymentUrlBuilder = $paymentUrlBuilder;
        $this->api = $api;
        $this->localeCodeHelper = $localeCodeHelper;
    }

    /** @throws PayPartsApiException */
    public function createFromCart(Cart $cart, SalesChannelContext $context): CreateSessionResponse
    {
        $salesChannelId = $context->getSalesChannel()->getId();
        $request = $this->buildRequestFromCart($cart, $context);

        return $this->executeSessionCreate($request, $salesChannelId);
    }

    /** @throws PayPartsApiException */
    public function createFromOrder(OrderEntity $order, SalesChannelContext $context): CreateSessionResponse
    {
        $salesChannelId = $context->getSalesChannel()->getId();
        $request = $this->buildRequestFromOrder($order, $context);

        return $this->executeSessionCreate($request, $salesChannelId);
    }

    // PAY.Parts always runs on production; the plugin's test mode doesn't apply (the config help text says so).
    public function getApiUrl(): string
    {
        return self::PRODUCTION_URL;
    }

    public function getSdkUrl(): string
    {
        return self::SDK_URL;
    }

    /**
     * No `order` block: PAY. only needs order lines for redirect methods (e.g. iDEAL); card sessions
     * are charged by `amount`, and the customer data is kept for 3DS/risk checks.
     */
    private function buildRequestFromCart(Cart $cart, SalesChannelContext $context): CreateSessionRequest
    {
        $amountCents = (int)round($cart->getPrice()->getTotalPrice() * 100);
        $currency = $context->getCurrency()->getIsoCode();
        $customer = $context->getCustomer();

        return new CreateSessionRequest(
            returnUrl: $this->paymentUrlBuilder->buildReturnUrl(''),
            exchangeUrl: $this->paymentUrlBuilder->buildExchangeUrl(),
            amount: new SessionAmount($amountCents, $currency),
            customer: $customer ? $this->buildCustomer($customer, $context) : null,
        );
    }

    private function buildRequestFromOrder(OrderEntity $order, SalesChannelContext $context): CreateSessionRequest
    {
        $amountCents = (int)round($order->getAmountTotal() * 100);
        // Order currency takes precedence; fall back to context when not yet associated.
        $currency = $order->getCurrency()?->getIsoCode() ?? $context->getCurrency()->getIsoCode();
        $customer = $context->getCustomer();

        return new CreateSessionRequest(
            returnUrl: $this->paymentUrlBuilder->buildReturnUrl(''),
            exchangeUrl: $this->paymentUrlBuilder->buildExchangeUrl(),
            amount: new SessionAmount($amountCents, $currency),
            reference: $order->getOrderNumber(),
            // The order's own billing address: the customer may have changed their default since ordering.
            customer: $customer ? $this->buildCustomer($customer, $context, $order->getBillingAddress()) : null,
        );
    }

    private function buildCustomer(
        CustomerEntity $customer,
        SalesChannelContext $context,
        ?OrderAddressEntity $orderBillingAddress = null
    ): SessionCustomer {
        $billing = $orderBillingAddress ?? $customer->getActiveBillingAddress() ?? $customer->getDefaultBillingAddress();
        // Extract the BCP 47 language subtag from "nl-NL", "de-DE", etc.
        // Not $context->getLanguageInfo(): it doesn't exist on Shopware 6.5.
        $localeCode = $this->localeCodeHelper->getLocaleCodeFromContext($context->getContext());
        $language = strtolower(substr($localeCode, 0, 2));

        return new SessionCustomer(
            locale: $language,
            firstName: $customer->getFirstName(),
            lastName: $customer->getLastName(),
            email: $customer->getEmail(),
            phone: $billing?->getPhoneNumber(),
            address: $billing ? $this->buildAddress($billing, $context->getSalesChannelId()) : null,
        );
    }

    private function buildAddress(CustomerAddressEntity|OrderAddressEntity $address, string $salesChannelId): SessionAddress
    {
        [$street, $houseNumber] = $this->parseStreetAndHouseNumber($address, $salesChannelId);

        return new SessionAddress(
            street: $street,
            houseNumber: $houseNumber,
            postalCode: $address->getZipcode() ?? '',
            city: $address->getCity() ?? '',
            country: $address->getCountry()?->getIso() ?? '',
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseStreetAndHouseNumber(CustomerAddressEntity|OrderAddressEntity $address, string $salesChannelId): array
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
            $this->getApiUrl(),
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
