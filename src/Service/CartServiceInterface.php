<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface CartServiceInterface
{
    /**
     * @param SalesChannelContext $context
     * @param string $paymentMethodID
     * @return SalesChannelContext
     */
    public function updatePaymentMethod(SalesChannelContext $context, string $paymentMethodID): SalesChannelContext;
}
