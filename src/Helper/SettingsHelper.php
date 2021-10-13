<?php

namespace PaynlPayment\Shopware6\Helper;

use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\ValueObjects\SettingsSelectOptionValueObject;

class SettingsHelper
{
    private $api;

    public function __construct(Api $api)
    {
        $this->api = $api;
    }

    public function getInstoreTerminalsOptions(?string $salesChannelId = null): array
    {
        if (empty($salesChannelId)) {
            return $this->getDefaultInstoreTerminalsOptions();
        }

        $terminals = $this->api->getInstoreTerminals($salesChannelId);
        $terminals = array_map(function ($terminal) {
            return (new SettingsSelectOptionValueObject($terminal['id'], $terminal['name']))->toArray();
        }, $terminals);

        return array_merge($this->getDefaultInstoreTerminalsOptions(), $terminals);
    }

    private function getDefaultInstoreTerminalsOptions(): array
    {
        return [
            (new SettingsSelectOptionValueObject('checkout', 'Choose in checkout'))->toArray(),
            (new SettingsSelectOptionValueObject('checkout_save', 'Choose in checkout and save'))->toArray()
        ];
    }
}
