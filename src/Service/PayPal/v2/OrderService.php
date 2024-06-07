<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PayPal\v2;

use GuzzleHttp\Client;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Order\CreateOrder;
use PaynlPayment\Shopware6\ValueObjects\PayPal\OrderDataMapper;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Response\CreateOrderResponse;

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

    public function create(CreateOrder $order, string $salesChannelId): CreateOrderResponse
    {
        $arrayResponse = $this->request(
            'POST',
            'v2/checkout/orders',
            $this->getBasicToken($salesChannelId),
            $order->toArray()
        );

        return $this->orderDataMapper->mapArray($arrayResponse);
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