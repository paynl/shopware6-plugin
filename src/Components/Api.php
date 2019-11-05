<?php

declare(strict_types=1);

namespace PaynlPayment\Components;

use Paynl\Config as SDKConfig;
use Paynl\Paymentmethods;

class Api
{
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @return mixed[]
     */
    public function getPaymentMethods(): array
    {
        // plugin doesn't configured, nothing to do
        if (empty($this->config->getTokenCode())
            || empty($this->config->getApiToken())
            || empty($this->config->getServiceId())) {
            return [];
        }

        SDKConfig::setTokenCode($this->config->getTokenCode());
        SDKConfig::setApiToken($this->config->getApiToken());
        SDKConfig::setServiceId($this->config->getServiceId());

        return Paymentmethods::getList();
    }
}
