<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PAY\v1;

use GuzzleHttp\Client;
use PaynlPayment\Shopware6\Components\Config;
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
