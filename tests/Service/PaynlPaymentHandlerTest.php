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
        return new PaynlPaymentHandler(
            $this->getOrderTransactionStateHandlerMock(),
            $this->getRouterInterfaceMock(),
            $this->getApiMock(),
            $this->getProcessingHelperMock()
        );
    }

    private function getOrderTransactionStateHandlerMock()
    {
        return \Mockery::mock(OrderTransactionStateHandler::class);
    }

    private function getRequestMock()
    {
        return \Mockery::mock(Request::class);
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
        return \Mockery::mock(PaynlTransactionEntity::class);
    }

    private function getDataBagMock()
    {
        return \Mockery::mock(RequestDataBag::class);
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
        return \Mockery::mock(Context::class);
    }
}
