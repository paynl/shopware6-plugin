<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PAY\v1;

use GuzzleHttp\Client;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Exceptions\PayPaymentApi;
use PaynlPayment\Shopware6\ValueObjects\PAY\OrderDataMapper;
use PaynlPayment\Shopware6\ValueObjects\PAY\Order\CreateOrder;
use PaynlPayment\Shopware6\ValueObjects\PAY\Response\CreateOrderResponse;

class OrderService extends BaseService
{
    private Config $config;
    private OrderDataMapper $createOrderDataMapper;

    public function __construct(Client $client, Config $config)
    {
        parent::__construct($client);

        $this->config = $config;
        $this->createOrderDataMapper = new OrderDataMapper();
    }

    /** @throws PayPaymentApi */
    public function create(CreateOrder $order, string $salesChannelId): CreateOrderResponse
    {
        $arrayResponse = $this->request(
            static::METHOD_POST,
            'v1/orders',
            $this->getBasicToken($salesChannelId),
            $order->toArray()
        );

        return $this->createOrderDataMapper->mapArray($arrayResponse);
    }

    /** @throws PayPaymentApi */
    public function getOrderStatus(string $transactionId, string $salesChannelId): array
    {
        return $this->request(
            static::METHOD_GET,
            'v1/orders/' . $transactionId . '/status',
            $this->getBasicToken($salesChannelId)
        );
    }

    private function getBasicToken(string $salesChannelId): string
    {
        return base64_encode(
            sprintf(
                "%s:%s",
                $this->config->getTokenCode($salesChannelId),
                $this->config->getApiToken($salesChannelId),
            )
        );
    }
}
