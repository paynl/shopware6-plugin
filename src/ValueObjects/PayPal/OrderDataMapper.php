<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects\PayPal;

use PaynlPayment\Shopware6\ValueObjects\PayPal\Order\Address;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Order\Amount;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Order\Payer;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Order\PurchaseUnit;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Order\Shipping;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Response\CreateOrderResponse;
use PaynlPayment\Shopware6\ValueObjects\PayPal\Response\OrderDetailResponse;

class OrderDataMapper
{
    public function mapCreateOrderArray(array $orderData): CreateOrderResponse
    {
        return new CreateOrderResponse(
            $orderData['id'] ?? '',
            $orderData['status'] ?? '',
            $orderData
        );
    }

    public function mapOrderDetailArray(array $orderData): OrderDetailResponse
    {
        $payerData = $orderData['payer'] ?? [];
        $payerNameData = $payerData['name'] ?? [];
        if ($payerData) {
            $payerPhoneData = $payerData['phone'] ?? [];
            $payerPhoneNumberData = $payerPhoneData['phone_number'] ?? [];

            $payerAddressData = $payerData['address'] ?? [];

            if ($payerAddressData) {
                $address = new Address(
                    $payerAddressData['address_line_1'] ?? null,
                    $payerAddressData['admin_area_1'] ?? null,
                    $payerAddressData['admin_area_2'] ?? null,
                    $payerAddressData['postal_code'] ?? null,
                    $payerAddressData['country_code'] ?? null,
                );
            }

            $payer = new Payer(
                (string) ($payerNameData['given_name'] ?? ''),
                (string) ($payerNameData['surname'] ?? ''),
                (string) ($payerData['email_address'] ?? ''),
                $payerPhoneNumberData['national_number'] ?? null,
                $address ?? null
            );
        }

        $purchaseUnitsData = $orderData['purchase_units'] ?? [];
        if ($purchaseUnitsData) {
            $purchaseUnits = [];
            foreach ($purchaseUnitsData as $purchaseUnitData) {
                $amountData = $purchaseUnitData['amount'] ?? [];

                $amount = new Amount(
                    $amountData['currency_code'] ?? '',
                    $amountData['value'] ?? ''
                );

                $shippingData = $purchaseUnitData['shipping'] ?? [];
                $shippingNameData = $shippingData['name'] ?? [];
                $shippingAddressData = $shippingData['address'] ?? [];

                if ($shippingAddressData) {
                    $address = new Address(
                        $shippingAddressData['address_line_1'] ?? null,
                        $shippingAddressData['admin_area_1'] ?? null,
                        $shippingAddressData['admin_area_2'] ?? null,
                        $shippingAddressData['postal_code'] ?? null,
                        $shippingAddressData['country_code'] ?? null,
                    );
                }

                $shipping = new Shipping(
                    $shippingNameData['full_name'] ?? null,
                        $address ?? null,
                );

                $purchaseUnits[] = new PurchaseUnit(
                    $amount,
                    $purchaseUnitData['reference_id'] ?? null,
                    $shipping
                );
            }
        }

        return new OrderDetailResponse(
            $orderData['id'] ?? '',
            $orderData['status'] ?? '',
            $orderData['intent'] ?? '',
            $payer ?? null,
            $purchaseUnits ?? [],
            $orderData
        );
    }
}