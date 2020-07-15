<?php

namespace PaynlPayment\Tests\Helpers;

use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Helper\CustomerHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Salutation\SalutationEntity;

class CustomerHelperTest extends TestCase
{
    private $salutationKey = 'F';
    private $femaleSalutation = ['mrs'];
    private $useAdditionalAddressFields = 1;
    private $iso = 'nl';
    private $zipcode = '200000';
    private $city = 'Amsterdam';
    private $street = 'SomeStreet';
    private $additionalAddressLine1 = 'SomeStreet1';
    private $additionalAddressLine2 = 'SomeStreet2';
    private $firstName = 'FirstName';
    private $lastName = 'LastName';
    private $email = 'Email';
    private $customerNumber = 'CustomerNumber';
    private $phone = '0501231212';

    public function testFormatAddresses()
    {
        $customerAddressEntityMock = $this->getCustomerAddressEntityMock($this->getCountryEntityMock());
        $customerEntityMock = $this->getCustomerEntityMock(
            $this->getSalutationEntityMock(),
            $customerAddressEntityMock
        );

        $customerHelper = new CustomerHelper($this->getConfigMock(), $this->getCustomerAddressRepositoryMock());
        $customerHelper->formatAddresses($customerEntityMock);

        // assert
        $this->assertTrue(true);
    }

    private function getSalutationEntityMock()
    {
        $salutationEntityMock = \Mockery::mock(SalutationEntity::class);
        $salutationEntityMock->shouldReceive('getSalutationKey')
            ->andReturn($this->salutationKey);

        return $salutationEntityMock;
    }

    private function getConfigMock()
    {
        $configMock = \Mockery::mock(Config::class);
        $configMock->shouldReceive('getFemaleSalutations')
            ->andReturn($this->femaleSalutation);
        $configMock->shouldReceive('getUseAdditionalAddressFields')
            ->andReturn($this->useAdditionalAddressFields);

        return $configMock;
    }

    private function getCustomerAddressRepositoryMock()
    {
        $customerAddressRepository = \Mockery::mock(EntityRepository::class);

        return $customerAddressRepository;
    }

    private function getCountryEntityMock()
    {
        $countryEntityMock = \Mockery::mock(CountryEntity::class);
        $countryEntityMock->shouldReceive('getIso')
            ->andReturn($this->iso);

        return $countryEntityMock;
    }

    private function getCustomerEntityMock($salutationEntityMock, $customerAddressEntityMock)
    {
        $customerMock = \Mockery::mock(CustomerEntity::class);
        $customerMock->shouldReceive('getSalutation')
            ->andReturn($salutationEntityMock);
        $customerMock->shouldReceive('getDefaultShippingAddress')
            ->andReturn($customerAddressEntityMock);
        $customerMock->shouldReceive('getFirstName')
            ->andReturn($this->firstName);
        $customerMock->shouldReceive('getLastName')
            ->andReturn($this->lastName);
        $customerMock->shouldReceive('getEmail')
            ->andReturn($this->email);
        $customerMock->shouldReceive('getCustomerNumber')
            ->andReturn($this->customerNumber);
        $customerMock->shouldReceive('getDefaultBillingAddress')
            ->andReturn($customerAddressEntityMock);

        return $customerMock;
    }

    private function getCustomerAddressEntityMock($countryEntityMock)
    {
        $customerAddressEntityMock = \Mockery::mock(CustomerAddressEntity::class);
        $customerAddressEntityMock->shouldReceive('getCountry')
            ->andReturn($countryEntityMock);
        $customerAddressEntityMock->shouldReceive('getCountry')
            ->andReturn($countryEntityMock);
        $customerAddressEntityMock->shouldReceive('getZipcode')
            ->andReturn($this->zipcode);
        $customerAddressEntityMock->shouldReceive('getCity')
            ->andReturn($this->city);
        $customerAddressEntityMock->shouldReceive('getStreet')
            ->andReturn($this->street);
        $customerAddressEntityMock->shouldReceive('getFirstName')
            ->andReturn($this->firstName);
        $customerAddressEntityMock->shouldReceive('getLastName')
            ->andReturn($this->lastName);
        $customerAddressEntityMock->shouldReceive('getPhoneNumber')
            ->andReturn($this->phone);
        $customerAddressEntityMock->shouldReceive('getAdditionalAddressLine1')
            ->andReturn($this->additionalAddressLine1);
        $customerAddressEntityMock->shouldReceive('getAdditionalAddressLine2')
            ->andReturn($this->additionalAddressLine2);

        return $customerAddressEntityMock;
    }
}
