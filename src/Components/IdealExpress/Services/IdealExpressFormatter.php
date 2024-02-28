<?php

namespace PaynlPayment\Shopware6\Components\IdealExpress\Services;

use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Symfony\Contracts\Translation\TranslatorInterface;

class IdealExpressFormatter
{
    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * @param ShippingMethodEntity $shippingMethod
     * @param float $shippingCosts
     * @return array<mixed>
     */
    public function formatShippingMethod(ShippingMethodEntity $shippingMethod, float $shippingCosts): array
    {
        $detail = '';

        if ($shippingMethod->getDeliveryTime() !== null) {
            $detail = $shippingMethod->getDeliveryTime()->getTranslation('name') ?: $shippingMethod->getDeliveryTime()->getName();
        }

        return [
            'identifier' => $shippingMethod->getId(),
            'label' => $shippingMethod->getName(),
            'amount' => $shippingCosts,
            'detail' => $shippingMethod->getDescription() . ($detail !== '' ? ' (' . $detail . ')' : ''),
        ];
    }
}
