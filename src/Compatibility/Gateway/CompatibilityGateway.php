<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Compatibility\Gateway;

use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CompatibilityGateway implements CompatibilityGatewayInterface
{
    /** @var string */
    private $swVersion;

    /** @var SalesChannelContextServiceInterface */
    private $contextService;

    public function __construct(string $swVersion, SalesChannelContextServiceInterface $contextService)
    {
        $this->swVersion = $swVersion;
        $this->contextService = $contextService;
    }

    public function getSalesChannelID(SalesChannelContext $context): string
    {
        return $context->getSalesChannel()->getId();
    }

    public function getSalesChannelContext(string $salesChannelID, string $token): SalesChannelContext
    {
        if ($this->versionGTE('6.4')) {
            $params = new SalesChannelContextServiceParameters($salesChannelID, $token);
            return $this->contextService->get($params);
        }

        /* @phpstan-ignore-next-line */
        $context = $this->contextService->get($salesChannelID, $token, null);

        return $context;
    }

    /**
     * @param string $version
     * @return bool
     */
    private function versionGTE(string $version): bool
    {
        return version_compare($this->swVersion, $version, '>=');
    }
}
