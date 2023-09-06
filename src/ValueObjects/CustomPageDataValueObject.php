<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects;

use Shopware\Core\Framework\Struct\Struct;

class CustomPageDataValueObject extends Struct
{
    /**
     * @var array
     */
    private $configs;

    /**
     * @var string
     */
    private $shopwareVersion;

    public function __construct(array $configs, string $shopwareVersion)
    {
        $this->configs = $configs;
        $this->shopwareVersion = $shopwareVersion;
    }

    public function getConfigs(): array
    {
        return $this->configs;
    }

    public function isSW64(): bool
    {
        return version_compare($this->shopwareVersion, '6.4', '>=');
    }

    public function isSW65(): bool
    {
        return version_compare($this->shopwareVersion, '6.5', '>=');
    }
}
