<?php

namespace PaynlPaymentShopware6\Tests\Components;

use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Exceptions\PaynlPaymentException;
use PaynlPayment\Shopware6\Helper\CustomerHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Framework\Context;
use Paynl\Result\Transaction\Start;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Contracts\Translation\TranslatorInterface;

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

    private $paymentMethodsList = [
        'PaymentMethod1'
    ];

    private $transactionId = 'transactionId';
    private $amount = 1;
    private $description = 'description';
    private $entityRepositoryInterfaceMock;
    private $customerHelperMock;
    private $currencyEntityMock;
    private $asyncPaymentTransactionStructMock;
    private $salesChannelContextMock;
    private $translator;
    private $session;
    private $orderEntityMock;

    public function setUp(): void
    {
        \Mockery::mock('alias:Paynl\Paymentmethods')
            ->shouldReceive('getList')
            ->andReturn($this->paymentMethodsList);

        \Mockery::mock('SDKConfig')->shouldReceive([
            'setTokenCode' => null,
            'setApiToken' => null,
            'setServiceId' => null
        ]);

        $startTransactionMock = \Mockery::mock(Start::class);
        \Mockery::mock('alias:Paynl\Transaction')
            ->shouldReceive('start')
            ->andReturn($startTransactionMock);

        $this->entityRepositoryInterfaceMock = \Mockery::mock( EntityRepositoryInterface::class);
        $this->customerHelperMock = \Mockery::mock(CustomerHelper::class);
        $this->currencyEntityMock = \Mockery::mock(PaymentMethodEntity::class)
            ->shouldReceive([
                'getIsoCode' => $this->isoCode
            ]);

        $this->orderEntityMock = \Mockery::mock(OrderEntity::class)
            ->shouldReceive('getId')->andReturn($this->orderId)
            ->shouldReceive('getAmountTotal')->andReturn($this->orderTotalAmount)
            ->shouldReceive('getOrderNumber')->andReturn($this->orderNumber);

        $this->asyncPaymentTransactionStructMock = \Mockery::mock(AsyncPaymentTransactionStruct::class)
            ->shouldReceive([
                'getOrder' => $this->orderEntityMock,
                'getReturnUrl' => $this->returnUrl
            ]);

        $paymentMethodEntityMock = \Mockery::mock(PaymentMethodEntity::class)
            ->shouldReceive([
                'getId' => $this->paymentMethodId
            ]);
        $currencyEntityMock = \Mockery::mock(PaymentMethodEntity::class)
            ->shouldReceive([
                'getIsoCode' => $this->isoCode
            ]);
        $contextMock = \Mockery::mock(Context::class);
        $customerEntityMock = \Mockery::mock(CustomerEntity::class);
        $this->translator = \Mockery::mock(TranslatorInterface::class);
        $this->session = \Mockery::mock(Session::class);

        $this->salesChannelContextMock = \Mockery::mock(SalesChannelContext::class)
            ->shouldReceive([
                'getPaymentMethod' => $paymentMethodEntityMock,
                'getCurrency' => $currencyEntityMock,
                'getContext' => $contextMock,
                'getCustomer' => $customerEntityMock,
            ]);
    }

    public function testExpectEmptyArrayIfWrongCredsFromGetPaymentMethods() {
        $this->apiInstance = new Api(
            $this->getConfigMock($this->tokenCode, $this->serviceId, $this->apiToken),
            $this->customerHelperMock,
            $this->entityRepositoryInterfaceMock,
            $this->translator,
            $this->session
        );

        $result = $this->apiInstance->getPaymentMethods();
        $this->assertTrue(empty($result), 'There are any of payment methods.');
    }

    public function testExpectPaynlPaymentExceptionInStartTransaction() {
        $this->apiInstance = new Api(
            $this->getConfigMock('', $this->serviceId, $this->apiToken),
            $this->customerHelperMock,
            $this->entityRepositoryInterfaceMock,
            $this->translator,
            $this->session
        );
        $this->expectException(PaynlPaymentException::class);
        $this->apiInstance->startTransaction(
            $this->asyncPaymentTransactionStructMock,
            $this->salesChannelContextMock,
            '/PaynlPayment/notify',
            '6.2.0',
            '0.3.0'
        );
        $this->assertFalse(false, 'Got an exception PaynlPaymentException.');
    }

    public function testExpectRefundNotAllowedInRefund() {
        $this->apiInstance = new Api(
            $this->getConfigMock('', $this->serviceId, $this->apiToken),
            $this->customerHelperMock,
            $this->entityRepositoryInterfaceMock,
            $this->translator,
            $this->session
        );
        $this->expectException(\Exception::class);
        $this->apiInstance->refund(
            $this->transactionId,
            $this->amount,
            $this->description
        );

        $this->assertFalse(false, 'Refund is not allowed.');
    }

    private function getConfigMock(string $tokenCode, string $apiToken, string $serviceId)
    {
        return \Mockery::mock(Config::class)->shouldReceive([
            'getTokenCode' => $tokenCode,
            'getApiToken' => $apiToken,
            'getServiceId' => $serviceId
        ]);
    }

    public function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
