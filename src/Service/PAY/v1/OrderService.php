<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PAY\v1;

use GuzzleHttp\Client;
use PaynlPayment\Shopware6\Components\Config;

class OrderService extends BaseService
{
    private Config $config;

    public function __construct(Client $client, Config $config)
    {
        parent::__construct($client);

        $this->config = $config;
    }

    public function create(array $orderData, string $salesChannelId): array
    {
        return $this->request('POST', 'v1/orders', $this->getBearerToken($salesChannelId), $orderData);
    }

    private function getBearerToken(string $salesChannelId): string
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
