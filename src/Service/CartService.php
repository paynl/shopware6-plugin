<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service;

use PaynlPayment\Shopware6\Compatibility\Gateway\CompatibilityGatewayInterface;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannel\SalesChannelContextSwitcher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartService implements CartServiceInterface
{
    /** @var SalesChannelContextSwitcher */
    private $contextSwitcher;

    /** @var CompatibilityGatewayInterface */
    private $compatibilityGateway;

    public function __construct(SalesChannelContextSwitcher $contextSwitcher, CompatibilityGatewayInterface $compatibilityGateway)
    {
        $this->contextSwitcher = $contextSwitcher;
        $this->compatibilityGateway = $compatibilityGateway;
    }

    public function updatePaymentMethod(SalesChannelContext $context, string $paymentMethodID): SalesChannelContext
    {
        $dataBag = new DataBag();

        $dataBag->add([
            SalesChannelContextService::PAYMENT_METHOD_ID => $paymentMethodID
        ]);

        $this->contextSwitcher->update($dataBag, $context);

        $scID = $this->compatibilityGateway->getSalesChannelID($context);

        return $this->compatibilityGateway->getSalesChannelContext($scID, $context->getToken());
    }
}
