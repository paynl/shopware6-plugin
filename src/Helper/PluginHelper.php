<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Helper;

class PluginHelper
{
    /**
     * @param string $defaultValue
     * @return string
     */
    public function getPluginVersionFromComposer(string $defaultValue = ''): string
    {
        $composerFilePath = sprintf('%s/%s', rtrim(__DIR__, '/'), '../../composer.json');
        if (file_exists($composerFilePath)) {
            $composer = json_decode(file_get_contents($composerFilePath), true);
            return $composer['version'] ?? $defaultValue;
        }

        return $defaultValue;
    }
}
