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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Paynl\Result\Transaction\Transaction as ResultTransaction;
use Paynl\Result\Transaction as Result;

class RefundControllerTest extends TestCase
{
    public function testCheckGetRefundDataMethod() {
        $notificationController = $this->getRefundControllerInstance();
        $responseInstance = $notificationController->getRefundData($this->getRequestMock());
        $this->assertInstanceOf(JsonResponse::class, $responseInstance);
    }

    public function testCheckRefundMethod() {
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
            $this->getPaynlApiMock(),
            $this->getPaynlConfigMock(),
            $this->getTransactionRepositoryMock(),
            $this->getProductRepositoryMock(),
            $this->getProcessingHelperMock()
        );
    }

    private function getPaynlApiMock()
    {
        $paynlApiMock = \Mockery::mock(Api::class);
        $paynlApiMock->shouldReceive('getTransaction')
            ->andReturn($this->getResultTransactionMock());
        $paynlApiMock->shouldReceive('refund')
            ->andReturn($this->getRefundMock());

        return $paynlApiMock;
    }

    private function getPaynlConfigMock()
    {
        return \Mockery::mock(Config::class);
    }

    private function getRefundMock()
    {
        return \Mockery::mock(Result\Refund::class);
    }

    private function getTransactionRepositoryMock()
    {
        $transactionRepositoryMock = \Mockery::mock(EntityRepositoryInterface::class);
        $transactionRepositoryMock->shouldReceive('search')
            ->andReturn($this->getEntitySearchResultMock());

        return $transactionRepositoryMock;
    }

    private function getEntitySearchResultMock()
    {
        $entitySearchResultMock = \Mockery::mock(EntitySearchResult::class);
        $entitySearchResultMock->shouldReceive('first')
            ->andReturn($this->getPaynlTransactionEntityMock());

        return $entitySearchResultMock;
    }

    private function getProductRepositoryMock()
    {
        return \Mockery::mock(EntityRepositoryInterface::class);
    }

    private function getPaynlTransactionEntityMock()
    {
        return \Mockery::mock(PaynlTransactionEntity::class);
    }

    private function getProcessingHelperMock()
    {
        $processingHelperMock = \Mockery::mock(ProcessingHelper::class);
        $processingHelperMock->shouldReceive('updateTransaction')
            ->andReturn(true);

        return $processingHelperMock;
    }

    private function getResultTransactionMock()
    {
        $resultTransactionMock = \Mockery::mock(ResultTransaction::class);
        $resultTransactionMock->shouldReceive('getAmount')
            ->andReturn(123);
        $resultTransactionMock->shouldReceive('getRefundedAmount')
            ->andReturn(50);

        return $resultTransactionMock;
    }
}
