<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Helper;

use PayNL\Sdk\Model\Address;
use PayNL\Sdk\Model\Customer;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Enums\CustomerCustomFieldsEnum;
use PaynlPayment\Shopware6\Repository\Customer\CustomerRepositoryInterface;
use PaynlPayment\Shopware6\Repository\CustomerAddress\CustomerAddressRepositoryInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Salutation\SalutationEntity;

class CustomerHelper
{
    private const CUSTOMER_NAME_MAX_LENGTH = 32;

    private Config $config;
    private CustomerAddressRepositoryInterface $customerAddressRepository;
    private CustomerRepositoryInterface $customerRepository;
    private TransactionLanguageHelper $transactionLanguageHelper;
    private IpSettingsHelper $ipSettingsHelper;

    public function __construct(
        Config $config,
        CustomerAddressRepositoryInterface $customerAddressRepository,
        CustomerRepositoryInterface $customerRepository,
        TransactionLanguageHelper $transactionLanguageHelper,
        IpSettingsHelper $ipSettingsHelper
    ) {
        $this->config = $config;
        $this->customerAddressRepository = $customerAddressRepository;
        $this->customerRepository = $customerRepository;
        $this->transactionLanguageHelper = $transactionLanguageHelper;
        $this->ipSettingsHelper = $ipSettingsHelper;
    }

    public function getCustomer(CustomerEntity $customerEntity, OrderEntity $orderEntity, string $salesChannelId): Customer
    {
        $gender = 'M';
        $femaleSalutations = $this->config->getFemaleSalutations($salesChannelId);
        /** @var SalutationEntity $salutation */
        $salutation = $customerEntity->getSalutation();
        if (in_array(trim($salutation->getSalutationKey()), $femaleSalutations)) {
            $gender = 'F';
        }

        $customer = new Customer();
        $customer->setFirstName($this->getValidStringValue($customerEntity->getFirstName()));
        $customer->setLastName($this->getValidStringValue($customerEntity->getLastName()));

        $birthDate = $customerEntity->getBirthday();
        if ($birthDate) {
            $customer->setBirthDate($birthDate->format('d-m-Y'));
        }

        $customer->setGender($gender);
        $customer->setPhone((string) $customerEntity->getDefaultBillingAddress()->getPhoneNumber());
        $customer->setEmail($customerEntity->getEmail());
        $customer->setReference($customerEntity->getCustomerNumber());

        $company = new \PayNL\Sdk\Model\Company();
        $company->setName((string) $customerEntity->getDefaultBillingAddress()->getCompany());
        $cocNumber = $customerEntity->getDefaultBillingAddress()->getCustomFields()['cocNumber'] ?? null;
        if ($cocNumber) {
            $company->setCoc((string) $cocNumber);
        }
        $company->setVat($this->getCustomerVatNumber($customerEntity));

        $customer->setCompany($company);

        if ($this->config->getPaymentScreenLanguage($salesChannelId)) {
            $customer->setLanguage($this->transactionLanguageHelper->getLanguageForOrder($orderEntity));
        }

        if ($this->ipSettingsHelper->getIp($salesChannelId)) {
            $customer->setIpAddress($this->ipSettingsHelper->getIp($salesChannelId));
        }

        return $customer;
    }

    /**
     * @param CustomerEntity $customer
     * @param string $salesChannelId
     * @return Address
     */
    public function getDeliveryAddress(CustomerEntity $customer, string $salesChannelId): Address
    {
        $houseNumberExtension = '';
        /** @var CustomerAddressEntity $customerShippingAddress */
        $customerShippingAddress = $customer->getDefaultShippingAddress();
        /** @var CountryEntity $country */
        $country = $customerShippingAddress->getCountry();
        $street = $customerShippingAddress->getStreet();
        if (!$this->config->getUseAdditionalAddressFields($salesChannelId)) {
            $address = paynl_split_address($street);
            $street = $address['street'] ?? '';
            $houseNumber = $address['number'] ?? '';

            $houseNumberArr = explode(' ', (string) $houseNumber);
            if (count($houseNumberArr) > 1) {
                $houseNumber = array_shift($houseNumberArr);
                $houseNumberExtension = implode(' ', $houseNumberArr);
            }
        } else {
            $houseNumber = $customerShippingAddress->getAdditionalAddressLine1();
            $houseNumberExtension = $customerShippingAddress->getAdditionalAddressLine2();
        }

        $devAddress = new \PayNL\Sdk\Model\Address();
        $devAddress->setStreetName($street);
        $devAddress->setStreetNumber($houseNumber);
        $devAddress->setStreetNumberExtension($houseNumberExtension);
        $devAddress->setZipCode($customerShippingAddress->getZipcode());
        $devAddress->setCity($customerShippingAddress->getCity());
        $devAddress->setCountryCode($country->getIso());

        return $devAddress;
    }

    /**
     * @param CustomerEntity $customer
     * @param string $salesChannelId
     * @return Address
     */
    public function getInvoiceAddress(CustomerEntity $customer, string $salesChannelId): Address
    {
        $houseNumberExtension = '';
        /** @var CustomerAddressEntity $customerBillingAddress */
        $customerBillingAddress = $customer->getDefaultBillingAddress();
        /** @var CountryEntity $country */
        $country = $customerBillingAddress->getCountry();
        $street = $customerBillingAddress->getStreet();
        if (!$this->config->getUseAdditionalAddressFields($salesChannelId)) {
            $address = paynl_split_address($street);
            $street = $address['street'] ?? '';
            $houseNumber = $address['number'] ?? '';

            $houseNumberArr = explode(' ', (string) $houseNumber);
            if (count($houseNumberArr) > 1) {
                $houseNumber = array_shift($houseNumberArr);
                $houseNumberExtension = implode(' ', $houseNumberArr);
            }
        } else {
            $houseNumber = $customerBillingAddress->getAdditionalAddressLine1();
            $houseNumberExtension = $customerBillingAddress->getAdditionalAddressLine2();
        }

        $invoiceAddress = new Address();
        $invoiceAddress->setStreetName($street);
        $invoiceAddress->setStreetNumber($houseNumber);
        $invoiceAddress->setStreetNumberExtension($houseNumberExtension);
        $invoiceAddress->setZipCode($customerBillingAddress->getZipcode());
        $invoiceAddress->setCity($customerBillingAddress->getCity());
        $invoiceAddress->setCountryCode($country->getIso());

        return $invoiceAddress;
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

    private function getValidStringValue(string $property): string
    {
        if (strlen($property) > self::CUSTOMER_NAME_MAX_LENGTH) {
            $property = substr($property, 0, self::CUSTOMER_NAME_MAX_LENGTH);
        }

        return $property;
    }
}
