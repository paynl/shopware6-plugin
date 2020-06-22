<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Helper;

use Paynl\Helper;
use PaynlPayment\Shopware6\Components\Config;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Salutation\SalutationEntity;

class CustomerHelper
{
    /** @var Config */
    private $config;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerAddressRepository;

    public function __construct(Config $config, EntityRepositoryInterface $customerAddressRepository)
    {
        $this->config = $config;
        $this->customerAddressRepository = $customerAddressRepository;
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
            'company' => [
                'name' => $customer->getDefaultBillingAddress()->getCompany(),
                'cocNumber' => $customer->getDefaultBillingAddress()->getCustomFields()['cocNumber'],
                'vatNumber' => $customer->getDefaultBillingAddress()->getVatId(),
            ],
            'address' => $this->getShippingAddress($customer),
            'invoiceAddress' => $this->getInvoiceAddress($customer, $gender)
        ];
    }

    public function saveCocNumber(string $addressId, string $cocNumber, Context $context): void
    {
        $criteria = (new Criteria());
        $criteria->addFilter(new EqualsFilter('id', $addressId));
        /** @var CustomerAddressEntity $addressEntity */
        $addressEntity = $this->customerAddressRepository->search($criteria, $context)->first();
        if ($addressEntity instanceof CustomerAddressEntity) {
            $customFields = $addressEntity->getCustomFields();
            $customFields['cocNumber'] = $cocNumber;
            $customFieldData = [
                'id' => $addressId,
                'customFields' => $customFields
            ];

            $this->customerAddressRepository->update([$customFieldData], $context);
        }
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
        if (!$this->config->getUseAdditionalAddressFields()) {
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
        if (!$this->config->getUseAdditionalAddressFields()) {
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
