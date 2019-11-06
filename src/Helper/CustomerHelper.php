<?php

declare(strict_types=1);

namespace PaynlPayment\Helper;

use Paynl\Helper;
use PaynlPayment\Components\Config;
use Shopware\Core\Checkout\Customer\CustomerEntity;

class CustomerHelper
{
    /** @var Config */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param CustomerEntity $customer
     * @return mixed[]
     */
    public function formatAddresses(CustomerEntity $customer): array
    {
        $gender = 'M';
        $femaleSalutations = $this->config->getFemaleSalutations();
        if (in_array(trim($customer->getSalutation()->getSalutationKey()), $femaleSalutations)) {
            $gender = 'F';
        }

        return [
            'enduser' => [
                'initials' => $customer->getFirstName(),
                'lastName' => $customer->getLastName(),
                'emailAddress' => $customer->getEmail(),
                'customerReference' => $customer->getCustomerNumber(),
                'gender' => $gender
            ],
            'address' => $this->getShippingAddress($customer),
            'invoiceAddress' => $this->getInvoiceAddress($customer, $gender)
        ];
    }

    private function getShippingAddress(CustomerEntity $customer)
    {
        $houseNumberExtension = '';
        $street = $customer->getDefaultShippingAddress()->getStreet();
        if (!$this->config->getUseAdditionalAddressFields()){
            $address = Helper::splitAddress($street);
            $street = $address[0] ?? '';
            $houseNumber = $address[1] ?? '';
        } else {
            $houseNumber = $customer->getDefaultShippingAddress()->getAdditionalAddressLine1();
            $houseNumberExtension = $customer->getDefaultShippingAddress()->getAdditionalAddressLine2();
        }


        return [
            'streetName' => $street,
            'houseNumber' => $houseNumber,
            'houseNumberExtension' => $houseNumberExtension,
            'zipCode' => $customer->getDefaultShippingAddress()->getZipcode(),
            'city' => $customer->getDefaultShippingAddress()->getCity(),
            'country' => $customer->getDefaultShippingAddress()->getCountry()->getIso()
        ];
    }

    private function getInvoiceAddress(CustomerEntity $customer, string $gender)
    {
        $houseNumberExtension = '';
        $street = $customer->getDefaultBillingAddress()->getStreet();
        if(!$this->config->getUseAdditionalAddressFields()){
            $address = Helper::splitAddress($street);
            $street = $address[0] ?? '';
            $houseNumber = $address[1] ?? '';
        } else {
            $houseNumber = $customer->getDefaultBillingAddress()->getAdditionalAddressLine1();
            $houseNumberExtension = $customer->getDefaultBillingAddress()->getAdditionalAddressLine2();
        }

        return  [
            'initials' => $customer->getDefaultBillingAddress()->getFirstName(),
            'lastName' => $customer->getDefaultBillingAddress()->getLastName(),
            'streetName' => $street,
            'houseNumber' => $houseNumber,
            'houseNumberExtension' => $houseNumberExtension,
            'zipCode' => $customer->getDefaultBillingAddress()->getZipcode(),
            'city' => $customer->getDefaultBillingAddress()->getCity(),
            'country' => $customer->getDefaultBillingAddress()->getCountry()->getIso(),
            'gender' => $gender
        ];
    }
}
