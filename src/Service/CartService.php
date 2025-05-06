<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service;

use PaynlPayment\Shopware6\Compatibility\Gateway\CompatibilityGatewayInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService as SalesChannelCartService;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannel\SalesChannelContextSwitcher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartService implements CartServiceInterface
{
    /** @var SalesChannelCartService */
    private $swCartService;

    /** @var SalesChannelContextSwitcher */
    private $contextSwitcher;

    /** @var ProductLineItemFactory */
    private $productItemFactory;

    /** @var CompatibilityGatewayInterface */
    private $compatibilityGateway;

    public function __construct(SalesChannelCartService $swCartService, SalesChannelContextSwitcher $contextSwitcher, ProductLineItemFactory $productItemFactory, CompatibilityGatewayInterface $compatibilityGateway)
    {
        $this->swCartService = $swCartService;
        $this->contextSwitcher = $contextSwitcher;
        $this->productItemFactory = $productItemFactory;
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

    public function addProduct(string $productId, int $quantity, SalesChannelContext $context): Cart
    {
        $cart = $this->getCalculatedMainCart($context);
        $data = [
            'id' => $productId,
            'referencedId' => $productId,
            'quantity' => $quantity
        ];

        $productItem = $this->productItemFactory->create($data, $context);

        return $this->swCartService->add($cart, $productItem, $context);
    }

    public function getCalculatedMainCart(SalesChannelContext $salesChannelContext): Cart
    {
        $cart = $this->swCartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

        return $this->swCartService->recalculate($cart, $salesChannelContext);
    }

    public function updateCart(Cart $cart): void
    {
        $this->swCartService->setCart($cart);
    }
}
