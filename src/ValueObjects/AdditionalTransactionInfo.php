<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects;

use Shopware\Core\Framework\Struct\Struct;

class AdditionalTransactionInfo extends Struct
{
    /** @var string */
    private $returnUrl;
    /** @var string */
    private $exchangeUrl;
    /** @var string */
    private $shopwareVersion;
    /** @var string */
    private $pluginVersion;
    /** @var string|null */
    private $terminalId;

    public function __construct(
        string  $returnUrl,
        string  $exchangeUrl,
        string  $shopwareVersion,
        string  $pluginVersion,
        ?string $terminalId
    ) {
        $this->returnUrl = $returnUrl;
        $this->exchangeUrl = $exchangeUrl;
        $this->shopwareVersion = $shopwareVersion;
        $this->pluginVersion = $pluginVersion;
        $this->terminalId = $terminalId;
    }

    public function getReturnUrl(): string
    {
        return $this->returnUrl;
    }

    public function getExchangeUrl(): string
    {
        return $this->exchangeUrl;
    }

    public function getShopwareVersion(): string
    {
        return $this->shopwareVersion;
    }

    public function getPluginVersion(): string
    {
        return $this->pluginVersion;
    }

    public function getTerminalId(): ?string
    {
        return $this->terminalId;
    }
}