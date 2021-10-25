<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Helper;

use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\ValueObjects\SettingsSelectOptionValueObject;

class SettingsHelper
{
    const TERMINAL_CHECKOUT_OPTION = 'checkout';
    const TERMINAL_CHECKOUT_SAVE_OPTION = 'checkout_save';

    const TERMINAL_CHECKOUT_LABEL = 'Choose in checkout';
    const TERMINAL_CHECKOUT_SAVE_LABEL = 'Choose in checkout and save';

    const TERMINAL_DEFAULT_OPTIONS = [
        self::TERMINAL_CHECKOUT_OPTION,
        self::TERMINAL_CHECKOUT_SAVE_OPTION,
    ];

    /** @var Api */
    private $api;

    public function __construct(Api $api)
    {
        $this->api = $api;
    }

    /**
     * @param string|null $salesChannelId
     * @return array
     */
    public function getTerminalsOptions(?string $salesChannelId = null): array
    {
        if (empty($salesChannelId)) {
            return $this->getDefaultTerminalsOptions();
        }

        $terminals = $this->api->getInstoreTerminals($salesChannelId);
        $terminals = array_map(function ($terminal) {
            return (new SettingsSelectOptionValueObject($terminal['id'], $terminal['name']))->toArray();
        }, $terminals);

        return array_merge($this->getDefaultTerminalsOptions(), $terminals);
    }

    /**
     * @return array
     */
    private function getDefaultTerminalsOptions(): array
    {
        return [
            (new SettingsSelectOptionValueObject(
                self::TERMINAL_CHECKOUT_OPTION,
                self::TERMINAL_CHECKOUT_LABEL)
            )->toArray(),
            (new SettingsSelectOptionValueObject(
                self::TERMINAL_CHECKOUT_SAVE_OPTION,
                self::TERMINAL_CHECKOUT_SAVE_LABEL)
            )->toArray()
        ];
    }
}
