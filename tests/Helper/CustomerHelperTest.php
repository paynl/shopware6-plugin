<?php

namespace PaynlPayment\Tests\Helpers;

use PaynlPayment\Helper\CustomerHelper;
use PHPUnit\Framework\TestCase;
use PaynlPayment\Components\Config;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\Salutation\SalutationEntity;

class CustomerHelperTest extends TestCase
{
    /**
     * @test
     */
    public function shouldPass()
    {
        //arrange
        $configMock = $this->createMock(Config::class);
        $configMock->method('getFemaleSalutations')
                   ->willReturn(['Siniora']);

        $salutationEntityMock = $this->createMock(SalutationEntity::class);
        $salutationEntityMock->method('getSalutationKey')
                             ->willReturn('S');

        $customerMock = $this->createMock(CustomerEntity::class);
        $customerMock->method('getSalutation')
                     ->willReturn($salutationEntityMock);


        $customerHelper = new CustomerHelper($configMock);

        //act
        $customerHelper->formatAddresses($customerMock);

        //assert
        $this->assertTrue(true);
    }
}