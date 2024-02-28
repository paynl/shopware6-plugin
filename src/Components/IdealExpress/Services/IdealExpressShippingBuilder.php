<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Components\IdealExpress\Services;

use PaynlPayment\Shopware6\Components\IdealExpress\Models\IdealExpressCart;
use PaynlPayment\Shopware6\Service\CartService;
use PaynlPayment\Shopware6\Service\ShippingMethodService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class IdealExpressShippingBuilder
{
    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var ShippingMethodService
     */
    private $shippingMethods;

    /**
     * @var IdealExpressFormatter
     */
    private $formatter;

    public function __construct(CartService $cartService, ShippingMethodService $shippingMethodService, IdealExpressFormatter $idealExpressFormatter)
    {
        $this->cartService = $cartService;
        $this->shippingMethods = $shippingMethodService;
        $this->formatter = $idealExpressFormatter;
    }

    public function buildIdealExpressCart(Cart $cart): IdealExpressCart
    {
        $idealCart = new IdealExpressCart();

        foreach ($cart->getLineItems() as $item) {
            if ($item->getPrice() instanceof CalculatedPrice) {
                $idealCart->addItem(
                    (string)$item->getReferencedId(),
                    (string)$item->getLabel(),
                    $item->getQuantity(),
                    $item->getPrice()->getUnitPrice()
                );
            }
        }

        foreach ($cart->getDeliveries() as $delivery) {
            $grossPrice = $delivery->getShippingCosts()->getUnitPrice();

            if ($grossPrice > 0) {
                $idealCart->addShipping(
                    (string)$delivery->getShippingMethod()->getName(),
                    $grossPrice
                );
            }
        }

        $taxes = $cart->getPrice()->getCalculatedTaxes()->getAmount();

        if ($taxes > 0) {
            $idealCart->setTaxes($taxes);
        }

        return $idealCart;
    }

    public function getShippingMethods(string $countryID, SalesChannelContext $context): array
    {
        $currentMethodID = $context->getShippingMethod()->getId();


        # switch to the correct country of the ideal express user
        $context = $this->cartService->updateCountry($context, $countryID);

        $selectedMethod = null;
        $allMethods = [];

        $availableShippingMethods = $this->shippingMethods->getActiveShippingMethods($context);

        foreach ($availableShippingMethods as $method) {
            # temporary switch to our shipping method.
            # we will then load the cart for this shipping method
            # in order to get the calculated shipping costs for this.
            $tempContext = $this->cartService->updateShippingMethod($context, $method->getId());
            $tempCart = $this->cartService->getCalculatedMainCart($tempContext);

            $shippingCosts = $this->cartService->getShippingCosts($tempCart);

            # format it for ideal express
            $formattedMethod = $this->formatter->formatShippingMethod($method, $shippingCosts);

            # either assign to our "selected" method which needs to be shown
            # first in the ideal express list, or to the rest which is
            # then appended after our default selection.
            if ($method->getId() === $currentMethodID) {
                $selectedMethod = $formattedMethod;
            } else {
                $allMethods[] = $formattedMethod;
            }
        }

        $finalMethods = [];

        # our pre-selected method always needs
        # to be the first item in the list
        if ($selectedMethod !== null) {
            $finalMethods[] = $selectedMethod;
        }

        foreach ($allMethods as $method) {
            $finalMethods[] = $method;
        }

        return $finalMethods;
    }
}
