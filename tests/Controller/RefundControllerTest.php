<?php

namespace PaynlPayment\Tests\Controller;

use PaynlPayment\Components\Api;
use PaynlPayment\Components\Config;
use PaynlPayment\Controller\RefundController;
use PaynlPayment\Entity\PaynlTransactionEntity;
use PaynlPayment\Helper\ProcessingHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Symfony\Component\HttpFoundation\Request;
use Paynl\Result\Transaction\Transaction as ResultTransaction;
use Paynl\Result\Transaction as Result;

class RefundControllerTest extends TestCase
{
    /**
     * @test
     */
    public function checkGetRefundDataMethod() {
        $notificationController = $this->getRefundControllerInstance();
        $notificationController->getRefundData($this->getRequestMock());

        $this->assertTrue(true);
    }
    /**
     * @test
     */
    public function checkRefundMethod() {
        $notificationController = $this->getRefundControllerInstance();
        $notificationController->refund($this->getRequestMock());

        $this->assertTrue(true);
    }

    private function getRequestMock()
    {
        $requestMock = \Mockery::mock(Request::class);
        $requestMock->shouldReceive('get')
            ->andReturn('transactionId');
        $requestMock->request = $this->getRequestRequestMock();

        return $requestMock;
    }

    private function getRequestRequestMock()
    {
        $requestMock = \Mockery::mock(Request::class);
        $requestMock->shouldReceive('all')
            ->andReturn([
                'transactionId' => 'transactionId',
                'amount' => 100,
                'description' => 'description',
                'products' => [],
            ]);

        return $requestMock;
    }

    private function getRefundControllerInstance()
    {
        return new RefundController(
            $this->paynlApiMock(),
            $this->paynlConfigMock(),
            $this->transactionRepositoryMock(),
            $this->productRepositoryMock(),
            $this->processingHelperMock()
        );
    }

    private function paynlApiMock()
    {
        $paynlApiMock = \Mockery::mock(Api::class);
        $paynlApiMock->shouldReceive('getTransaction')
            ->andReturn($this->resultTransactionMock());
        $paynlApiMock->shouldReceive('refund')
            ->andReturn($this->refundMock());

        return $paynlApiMock;
    }

    private function paynlConfigMock()
    {
        return \Mockery::mock(Config::class);
    }

    private function refundMock()
    {
        return \Mockery::mock(Result\Refund::class);
    }

    private function transactionRepositoryMock()
    {
        $transactionRepositoryMock = \Mockery::mock(EntityRepositoryInterface::class);
        $transactionRepositoryMock->shouldReceive('search')
            ->andReturn($this->entitySearchResultMock());

        return $transactionRepositoryMock;
    }

    private function entitySearchResultMock()
    {
        $entitySearchResultMock = \Mockery::mock(EntitySearchResult::class);
        $entitySearchResultMock->shouldReceive('first')
            ->andReturn($this->paynlTransactionEntityMock());

        return $entitySearchResultMock;
    }

    private function productRepositoryMock()
    {
        return \Mockery::mock(EntityRepositoryInterface::class);
    }

    private function paynlTransactionEntityMock()
    {
        return \Mockery::mock(PaynlTransactionEntity::class);
    }

    private function processingHelperMock()
    {
        $processingHelperMock = \Mockery::mock(ProcessingHelper::class);
        $processingHelperMock->shouldReceive('updateTransaction')
            ->andReturn(true);

        return $processingHelperMock;
    }

    private function resultTransactionMock()
    {
        $resultTransactionMock = \Mockery::mock(ResultTransaction::class);
        $resultTransactionMock->shouldReceive('getAmount')
            ->andReturn(123);
        $resultTransactionMock->shouldReceive('getRefundedAmount')
            ->andReturn(50);

        return $resultTransactionMock;
    }
}
