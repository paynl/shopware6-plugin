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
               'AQGA-UwfmmgYrwNAZQSUG6rcGJi3-xMv2VG-Rj-5A-eRe_Uasi16czapjawsTV76IXyi7difEPw_vRp4',
                'EIqnst50TtFXZS1sUuNY_XhnOLPZio4FT3IlgrQzDy6qe8Z_RIWy8inOK1xatny1X8a1GSC1O5lYK5Xk',
            )
        );
    }
}