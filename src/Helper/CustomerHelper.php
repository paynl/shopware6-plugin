<?php declare(strict_types=1);

namespace PaynlPayment\Helper;

use Paynl\Helper;
use PaynlPayment\Components\Config;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Salutation\SalutationEntity;

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
        /** @var SalutationEntity $salutation */
        $salutation = $customer->getSalutation();
        if (in_array(trim($salutation->getSalutationKey()), $femaleSalutations)) {
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

    /**
     * @param CustomerEntity $customer
     * @return mixed[]
     */
    private function getShippingAddress(CustomerEntity $customer): array
    {
        $houseNumberExtension = '';
        /** @var CustomerAddressEntity $customerShippingAddress */
        $customerShippingAddress = $customer->getDefaultShippingAddress();
        /** @var CountryEntity $country */
        $country = $customerShippingAddress->getCountry();
        $street = $customerShippingAddress->getStreet();
        if (!$this->config->getUseAdditionalAddressFields()){
            $address = Helper::splitAddress($street);
            $street = $address[0] ?? '';
            $houseNumber = $address[1] ?? '';
        } else {
            $houseNumber = $customerShippingAddress->getAdditionalAddressLine1();
            $houseNumberExtension = $customerShippingAddress->getAdditionalAddressLine2();
        }


        return [
            'streetName' => $street,
            'houseNumber' => $houseNumber,
            'houseNumberExtension' => $houseNumberExtension,
            'zipCode' => $customerShippingAddress->getZipcode(),
            'city' => $customerShippingAddress->getCity(),
            'country' => $country->getIso()
        ];
    }

    /**
     * @param CustomerEntity $customer
     * @param string $gender
     * @return mixed[]
     */
    private function getInvoiceAddress(CustomerEntity $customer, string $gender): array
    {
        $houseNumberExtension = '';
        /** @var CustomerAddressEntity $customerBillingAddress */
        $customerBillingAddress = $customer->getDefaultBillingAddress();
        /** @var CountryEntity $country */
        $country = $customerBillingAddress->getCountry();
        $street = $customerBillingAddress->getStreet();
        if(!$this->config->getUseAdditionalAddressFields()){
            $address = Helper::splitAddress($street);
            $street = $address[0] ?? '';
            $houseNumber = $address[1] ?? '';
        } else {
            $houseNumber = $customerBillingAddress->getAdditionalAddressLine1();
            $houseNumberExtension = $customerBillingAddress->getAdditionalAddressLine2();
        }

        return  [
            'initials' => $customerBillingAddress->getFirstName(),
            'lastName' => $customerBillingAddress->getLastName(),
            'streetName' => $street,
            'houseNumber' => $houseNumber,
            'houseNumberExtension' => $houseNumberExtension,
            'zipCode' => $customerBillingAddress->getZipcode(),
            'city' => $customerBillingAddress->getCity(),
            'country' => $country->getIso(),
            'gender' => $gender
        ];
    }
}
