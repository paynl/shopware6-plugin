<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Compatibility\Gateway;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface CompatibilityGatewayInterface
{
    public function getSalesChannelID(SalesChannelContext $context): string;

    public function getSalesChannelContext(string $salesChannelID, string $token): SalesChannelContext;
}
