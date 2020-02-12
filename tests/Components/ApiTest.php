<?php

namespace PaynlPayment\Tests\Components;

use PaynlPayment\Components\Api;
use PaynlPayment\Components\Config;
use PaynlPayment\Helper\CustomerHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Framework\Context;
use Paynl\Result\Transaction\Start;

/**
 * Prevent setting the class alias for all test suites
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ApiTest extends TestCase
{
    private $apiInstance;

    private $tokenCode = 'test';
    private $serviceId = 'test';
    private $apiToken = 'test';
    private $isoCode = 'eur';

    private $orderId = 'someOrderId';
    private $orderTotalAmount = 1;
    private $orderNumber = 1;

    private $returnUrl = '/finalize';

    private $paymentMethodId = 'SomePaymentMethodId';

    private $paymentMethodsList = [];
    private $transactionId = 'transactionId';
    private $amount = 1;
    private $description = 'description';

    public function setUp(): void
    {
        \Mockery::mock('alias:Paynl\Paymentmethods')
            ->shouldReceive('getList')
            ->andReturn($this->paymentMethodsList);

        $SDKConfig = \Mockery::mock('SDKConfig');
        $SDKConfig->shouldReceive('setTokenCode');
        $SDKConfig->shouldReceive('setApiToken');
        $SDKConfig->shouldReceive('setServiceId');

        $result = \Mockery::mock('alias:Paynl\Transaction');
        $result->shouldReceive('start')
            ->andReturn($this->getStartMock());
    }

    public function testExpectEmptyArrayFromGetPaymentMethods() {
        $this->apiInstance = new Api(
            $this->getConfigMock($this->tokenCode, $this->serviceId, $this->apiToken),
            $this->getCustomerHelperMock(),
            $this->getEntityRepositoryInterfaceMock()
        );

        $result = $this->apiInstance->getPaymentMethods();
        $this->assertTrue(empty($result), 'There aren\'t any of payment methods.');
    }

    public function testExpectPaynlPaymentExceptionInStartTransaction() {
        $this->apiInstance = new Api(
            $this->getConfigMock('', $this->serviceId, $this->apiToken),
            $this->getCustomerHelperMock(),
            $this->getEntityRepositoryInterfaceMock()
        );
        $this->expectException(\PaynlPayment\Exceptions\PaynlPaymentException::class);
        $this->apiInstance->startTransaction(
            $this->getAsyncPaymentTransactionStructMock(),
            $this->getSalesChannelContextMock(),
            '/'
        );
        $this->assertFalse(false, 'Got an exception PaynlPaymentException.');
    }

    public function testExpectRefundNotAllowedInRefund() {
        $this->apiInstance = new Api(
            $this->getConfigMock('', $this->serviceId, $this->apiToken),
            $this->getCustomerHelperMock(),
            $this->getEntityRepositoryInterfaceMock()
        );
        $this->expectException(\Exception::class);
        $this->apiInstance->refund(
            $this->transactionId,
            $this->amount,
            $this->description
        );

        $this->assertFalse(false, 'Refund is not allowed.');
    }

    private function getAsyncPaymentTransactionStructMock()
    {
        $asyncPaymentTransactionStructMock = \Mockery::mock(AsyncPaymentTransactionStruct::class);
        $asyncPaymentTransactionStructMock->shouldReceive('getOrder')
            ->andReturn($this->getOrderEntityMock());
        $asyncPaymentTransactionStructMock->shouldReceive('getReturnUrl')
            ->andReturn($this->returnUrl);

        return $asyncPaymentTransactionStructMock;
    }

    private function getStartMock()
    {
        return \Mockery::mock(Start::class);
    }

    private function getSalesChannelContextMock()
    {
        $salesChannelContextMock = \Mockery::mock(SalesChannelContext::class);
        $salesChannelContextMock->shouldReceive('getPaymentMethod')->andReturn($this->getPaymentMethodEntityMock());
        $salesChannelContextMock->shouldReceive('getCurrency')->andReturn($this->getCurrencyEntityMock());
        $salesChannelContextMock->shouldReceive('getContext')->andReturn($this->getContextMock());
        $salesChannelContextMock->shouldReceive('getCustomer')->andReturn($this->getCustomerEntityMock());

        return $salesChannelContextMock;
    }

    private function getPaymentMethodEntityMock()
    {
        $paymentMethodEntityMock = \Mockery::mock(PaymentMethodEntity::class);
        $paymentMethodEntityMock->shouldReceive('getId')->andReturn($this->paymentMethodId);

        return $paymentMethodEntityMock;
    }

    private function getOrderEntityMock()
    {
        $orderEntityMock = \Mockery::mock(OrderEntity::class);
        $orderEntityMock->shouldReceive('getId')->andReturn($this->orderId);
        $orderEntityMock->shouldReceive('getAmountTotal')->andReturn($this->orderTotalAmount);
        $orderEntityMock->shouldReceive('getOrderNumber')->andReturn($this->orderNumber);

        return $orderEntityMock;
    }

    private function getContextMock()
    {
        $contextMock = \Mockery::mock(Context::class);

        return $contextMock;
    }

    private function getCustomerEntityMock()
    {
        $customerEntityMock = \Mockery::mock(CustomerEntity::class);

        return $customerEntityMock;
    }

    private function getCurrencyEntityMock()
    {
        $currencyEntityMock = \Mockery::mock(PaymentMethodEntity::class);
        $currencyEntityMock->shouldReceive('getIsoCode')->andReturn($this->isoCode);

        return $currencyEntityMock;
    }

    private function getConfigMock(string $tokenCode, string $apiToken, string $serviceId)
    {
        $configMock = \Mockery::mock(Config::class);
        $configMock->shouldReceive('getTokenCode')->andReturn($tokenCode);
        $configMock->shouldReceive('getApiToken')->andReturn($apiToken);
        $configMock->shouldReceive('getServiceId')->andReturn($serviceId);

        return $configMock;
    }

    private function getCustomerHelperMock()
    {
        $customerHelperMock = \Mockery::mock(CustomerHelper::class);

        return $customerHelperMock;
    }

    private function getEntityRepositoryInterfaceMock()
    {
        $entityRepositoryInterfaceMock = \Mockery::mock(EntityRepositoryInterface::class);

        return $entityRepositoryInterfaceMock;
    }

    public function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
