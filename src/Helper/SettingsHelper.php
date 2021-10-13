<?php

namespace PaynlPayment\Shopware6\Helper;

use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\ValueObjects\SettingsSelectOptionValueObject;

class SettingsHelper
{
    const TERMINAL_CHECKOUT_OPTION = 'checkout';
    const TERMINAL_CHECKOUT_SAVE_OPTION = 'checkout_save';

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
            (new SettingsSelectOptionValueObject(
                self::TERMINAL_CHECKOUT_OPTION,
                'Choose in checkout')
            )->toArray(),
            (new SettingsSelectOptionValueObject(
                self::TERMINAL_CHECKOUT_SAVE_OPTION,
                'Choose in checkout and save')
            )->toArray()
        ];
    }
}
