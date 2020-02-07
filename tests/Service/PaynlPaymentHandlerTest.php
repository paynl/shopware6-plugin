<?php

namespace PaynlPayment\Tests\Service;

use Paynl\Result\Transaction\Start;
use PaynlPayment\Components\Api;
use PaynlPayment\Entity\PaynlTransactionEntity;
use PaynlPayment\Helper\ProcessingHelper;
use PaynlPayment\Service\PaynlPaymentHandler;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\Request;
use Shopware\Core\Framework\Context;

class PaynlPaymentHandlerTest extends TestCase
{
    /**
     * @test
     */
    public function checkPayMethod() {
        $paynlPaymentHandler = $this->getPaynlPaymentInstance();

        $paynlPaymentHandler->pay(
            $this->getAsyncPaymentTransactionStructMock(),
            $this->getDataBagMock(),
            $this->getSalesChannelContextMock()
        );

        $this->assertTrue(true);
    }
    /**
     * @test
     */
    public function checkFinalizeMethod() {
        $paynlPaymentHandler = $this->getPaynlPaymentInstance();

        $paynlPaymentHandler->finalize(
            $this->getAsyncPaymentTransactionStructMock(),
            $this->getRequestMock(),
            $this->getSalesChannelContextMock()
        );

        $this->assertTrue(true);
    }

    private function getPaynlPaymentInstance()
    {
        $orderTransactionStateHandlerMock = $this->getOrderTransactionStateHandlerMock();
        $routerMock = $this->getRouterInterfaceMock();
        $apiMock = $this->getApiMock();
        $processingHelperMock = $this->getProcessingHelperMock();

        return new PaynlPaymentHandler($orderTransactionStateHandlerMock, $routerMock, $apiMock, $processingHelperMock);
    }

    private function getOrderTransactionStateHandlerMock()
    {
        $orderTransactionStateHandlerMock = \Mockery::mock(OrderTransactionStateHandler::class);

        return $orderTransactionStateHandlerMock;
    }

    private function getRequestMock()
    {
        $requestMock = \Mockery::mock(Request::class);

        return $requestMock;
    }

    private function getApiMock()
    {
        $apiMock = \Mockery::mock(Api::class);
        $apiMock->shouldReceive('startTransaction')
            ->andReturn($this->getApiStartMock());

        return $apiMock;
    }

    private function getApiStartMock()
    {
        $apiStartMock = \Mockery::mock(Start::class);
        $apiStartMock->shouldReceive('getTransactionId')
            ->andReturn('testTransactionId');
        $apiStartMock->shouldReceive('getRedirectUrl')
            ->andReturn('/');

        return $apiStartMock;
    }

    private function getProcessingHelperMock()
    {
        $processingHelperMock = \Mockery::mock(ProcessingHelper::class);
        $processingHelperMock->shouldReceive('storePaynlTransactionData')
            ->andReturn(true);
        $processingHelperMock->shouldReceive('findTransactionByOrderId')
            ->andReturn($this->paynlTransactionEntityMock());
        $processingHelperMock->shouldReceive('updateTransaction')
            ->andReturn('');

        return $processingHelperMock;
    }

    private function getRouterInterfaceMock()
    {
        $routerInterfaceMock = \Mockery::mock(RouterInterface::class);
        $routerInterfaceMock->shouldReceive('generate')
            ->andReturn('/notify');

        return $routerInterfaceMock;
    }

    private function paynlTransactionEntityMock()
    {
        $paynlTransactionEntityMock = \Mockery::mock(PaynlTransactionEntity::class);

        return $paynlTransactionEntityMock;
    }

    private function getDataBagMock()
    {
        $dataBag = \Mockery::mock(RequestDataBag::class);

        return $dataBag;
    }

    private function getAsyncPaymentTransactionStructMock()
    {
        $asyncPaymentTransactionStructMock = \Mockery::mock(AsyncPaymentTransactionStruct::class);
        $asyncPaymentTransactionStructMock->shouldReceive('getOrderTransaction')
            ->andReturn($this->getOrderTransactionEntityMock());
        $asyncPaymentTransactionStructMock->shouldReceive('getOrder')
            ->andReturn($this->getOrderEntityMock());

        return $asyncPaymentTransactionStructMock;
    }

    private function getOrderTransactionEntityMock()
    {
        $orderTransactionEntityMock = \Mockery::mock(OrderTransactionEntity::class);
        $orderTransactionEntityMock->shouldReceive('getId')
            ->andReturn(1);

        return $orderTransactionEntityMock;
    }

    private function getOrderEntityMock()
    {
        $orderEntityMock = \Mockery::mock(OrderEntity::class);
        $orderEntityMock->shouldReceive('getId')
            ->andReturn(1);

        return $orderEntityMock;
    }

    private function getSalesChannelContextMock()
    {
        $salesChannelContextMock = \Mockery::mock(SalesChannelContext::class);
        $salesChannelContextMock->shouldReceive('getContext')
            ->andReturn($this->getContextMock());

        return $salesChannelContextMock;
    }

    private function getContextMock()
    {
        $contextMock = \Mockery::mock(Context::class);

        return $contextMock;
    }
}
