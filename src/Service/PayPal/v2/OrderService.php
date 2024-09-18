<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PayPal\v2;

use GuzzleHttp\Client;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Exceptions\PayPalPaymentApi;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Order\CreateOrder;
use PaynlPayment\Shopware6\ValueObjects\PayPal\OrderDataMapper;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Response\CreateOrderResponse;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Response\OrderDetailResponse;

class OrderService extends BaseService
{
    private Config $config;
    private OrderDataMapper $orderDataMapper;

    public function __construct(Client $client, Config $config)
    {
        parent::__construct($client);

        $this->config = $config;
        $this->orderDataMapper = new OrderDataMapper();
    }

    /** @throws PayPalPaymentApi */
    public function create(CreateOrder $order, string $salesChannelId): CreateOrderResponse
    {
        $arrayResponse = $this->request(
            static::METHOD_POST,
            'v2/checkout/orders',
            $this->getBasicToken($salesChannelId),
            $order->toArray()
        );

        return $this->orderDataMapper->mapCreateOrderArray($arrayResponse);
    }

    /** @throws PayPalPaymentApi */
    public function getOrder(string $orderId, string $salesChannelId): OrderDetailResponse
    {
        $arrayResponse = $this->request(
            static::METHOD_GET,
            "v2/checkout/orders/{$orderId}",
            $this->getBasicToken($salesChannelId)
        );

        return $this->orderDataMapper->mapOrderDetailArray($arrayResponse);
    }

    private function getBasicToken(string $salesChannelId): string
    {
        $testMode = $this->config->getTestMode($salesChannelId);

        $payPalClientId = $testMode
            ? $this->config->getPaymentPayPalClientIdSandbox($salesChannelId)
            : $this->config->getPaymentPayPalClientIdProduction($salesChannelId);

        $payPalSecretKey = $testMode
            ? $this->config->getPaymentPayPalSecretKeySandbox($salesChannelId)
            : $this->config->getPaymentPayPalSecretKeyProduction($salesChannelId);

        return base64_encode(sprintf("%s:%s", $payPalClientId, $payPalSecretKey));
    }
}