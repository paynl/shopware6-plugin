<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Components\ConfigReader;

use PaynlPayment\Shopware6\Struct\Configuration;

interface ConfigReaderInterface
{
    public function read(string $salesChannelId = '', bool $fallback = true): Configuration;
}
