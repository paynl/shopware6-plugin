<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Helper;

use Paynl\Helper;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Enums\CustomerCustomFieldsEnum;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Salutation\SalutationEntity;

class CustomerHelper
{
    private const BIRTHDATE_FORMAT = 'd-m-Y';

    /** @var Config */
    private $config;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerAddressRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    public function __construct(
        Config $config,
        EntityRepositoryInterface $customerAddressRepository,
        EntityRepositoryInterface $customerRepository
    ) {
        $this->config = $config;
        $this->customerAddressRepository = $customerAddressRepository;
        $this->customerRepository = $customerRepository;
    }

    /**
     * @param CustomerEntity $customer
     * @return mixed[]
     */
    public function formatAddresses(CustomerEntity $customer, string $salesChannelId): array
    {
        $gender = 'M';
        $femaleSalutations = $this->config->getFemaleSalutations($salesChannelId);
        /** @var SalutationEntity $salutation */
        $salutation = $customer->getSalutation();
        if (in_array(trim($salutation->getSalutationKey()), $femaleSalutations)) {
            $gender = 'F';
        }

        $formattedAddress = [
            'enduser' => [
                'initials' => $customer->getFirstName(),
                'lastName' => $customer->getLastName(),
                'emailAddress' => $customer->getEmail(),
                'customerReference' => $customer->getCustomerNumber(),
                'gender' => $gender,
                'phoneNumber' => $customer->getDefaultBillingAddress()->getPhoneNumber(),
            ],
            'company' => [
                'name' => $customer->getDefaultBillingAddress()->getCompany(),
                'vatNumber' => $this->getCustomerVatNumber($customer),
            ],
            'address' => $this->getShippingAddress($customer, $salesChannelId),
            'invoiceAddress' => $this->getInvoiceAddress($customer, $gender, $salesChannelId)
        ];

        $cocNumber = $customer->getDefaultBillingAddress()->getCustomFields()['cocNumber'] ?? null;
        if (!empty($cocNumber)) {
            $formattedAddress['company']['cocNumber'] = $cocNumber;
        }

        $birthDate = $customer->getBirthday();
        if (!empty($birthDate)) {
            $formattedAddress['enduser']['birthDate'] = $birthDate->format(self::BIRTHDATE_FORMAT);
        }

        return $formattedAddress;
    }

    public function saveCocNumber(CustomerAddressEntity $customerAddress, string $cocNumber, Context $context): void
    {
        $customFields = $customerAddress->getCustomFields();
        $customFields['cocNumber'] = $cocNumber;
        $customFieldData = [
            'id' => $customerAddress->getId(),
            'customFields' => $customFields
        ];

        $this->customerAddressRepository->update([$customFieldData], $context);
    }

    public function saveCustomerPhone(CustomerAddressEntity $customerAddress, string $phone, Context $context): void
    {
        if (empty($phone)) {
            return;
        }

        $customerAddressData = [
            'id' => $customerAddress->getId(),
            'phoneNumber' => $phone
        ];

        $this->customerAddressRepository->update([$customerAddressData], $context);
    }

    public function saveCustomerBirthdate(CustomerEntity $customer, string $dob, Context $context): void
    {
        if (empty($dob)) {
            return;
        }

        $customerData = [
            'id' => $customer->getId(),
            'birthday' => $dob
        ];

        $this->customerRepository->update([$customerData], $context);
    }

    public function savePaynlIssuer(
        CustomerEntity $customer,
        string $paymentMethodId,
        string $issuer,
        Context $context
    ): void {
        $this->savePaymentMethodSelectedData($paymentMethodId, 'issuer', $issuer, $customer, $context);
    }

    public function savePaynlInstoreTerminal(
        CustomerEntity $customer,
        string $paymentMethodId,
        string $terminal,
        Context $context
    ): void {
        $this->savePaymentMethodSelectedData($paymentMethodId, 'terminal', $terminal, $customer, $context);
    }

    private function savePaymentMethodSelectedData(
        string $paymentMethodId,
        string $name,
        string $data,
        CustomerEntity $customer,
        Context $context
    ): void {
        if (empty($data)) {
            return;
        }

        $customFields = $customer->getCustomFields();
        $customFields[CustomerCustomFieldsEnum::PAYMENT_METHODS_SELECTED_DATA][$paymentMethodId][$name] = $data;

        $this->customerRepository->upsert([[
            'id' => $customer->getId(),
            'customFields' => $customFields
        ]], $context);
    }

    /**
     * @param CustomerEntity $customer
     * @return mixed[]
     */
    private function getShippingAddress(CustomerEntity $customer, string $salesChannelId): array
    {
        $houseNumberExtension = '';
        /** @var CustomerAddressEntity $customerShippingAddress */
        $customerShippingAddress = $customer->getDefaultShippingAddress();
        /** @var CountryEntity $country */
        $country = $customerShippingAddress->getCountry();
        $street = $customerShippingAddress->getStreet();
        if (!$this->config->getUseAdditionalAddressFields($salesChannelId)) {
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
    private function getInvoiceAddress(CustomerEntity $customer, string $gender, string $salesChannelId): array
    {
        $houseNumberExtension = '';
        /** @var CustomerAddressEntity $customerBillingAddress */
        $customerBillingAddress = $customer->getDefaultBillingAddress();
        /** @var CountryEntity $country */
        $country = $customerBillingAddress->getCountry();
        $street = $customerBillingAddress->getStreet();
        if (!$this->config->getUseAdditionalAddressFields($salesChannelId)) {
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

    private function getCustomerVatNumber(CustomerEntity $customer): string
    {
        if (method_exists($customer, 'getVatIds')) {
            $customerVatIds = (array)$customer->getVatIds();

            return (string)reset($customerVatIds);
        }

        if (method_exists($customer->getDefaultBillingAddress(), 'getVatId')) {
            return (string)$customer->getDefaultBillingAddress()->getVatId();
        }

        return '';
    }
}
