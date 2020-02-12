<?php

namespace PaynlPayment\Tests\Helpers;

use PaynlPayment\Components\Api;
use PaynlPayment\Entity\PaynlTransactionEntity;
use PaynlPayment\Helper\ProcessingHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\Framework\Context;
use Paynl\Result\Transaction\Transaction as ResultTransaction;

class ProcessingHelperTest extends TestCase
{
    private $paynlTransactionId = '123123wqeq123123';
    private $orderTransactionId = '22b7cfd3aff04368a1f661f4e644f0a0';
    private $exception = null;
    private $orderId = '22b7cfd3aff04368a1f661f4e644f0a0';
    private $paynlPaymentMethodId = '10';
    private $amountTotal = '10';
    private $currencyIsoCode = 'EUR';
    private $stateId = '10';
    private $shippingMethodId = '22b7cfd3aff04368a1f661f4e644f0a0';
    private $customerId = '22b7cfd3aff04368a1f661f4e644f0a0';

    public function testUpdateTransaction()
    {
        $isExchange = false;
        $transactionMock = $this->getResultTransactionMock();
        $apiMock = $this->getApiMock($transactionMock);
        $entityWrittenContainerEventMock = $this->getEntityWrittenContainerEventMock();
        $paynlTransactionMock = $this->getPaynlTransactionMock();
        $entitySearchResultMock = $this->getEntitySearchResultMock($paynlTransactionMock);
        $entityRepositoryInterfaceMock = $this->getEntityRepositoryInterfaceMock(
            $entityWrittenContainerEventMock,
            $entitySearchResultMock
        );
        $stateMachineRegistryMock = $this->getStateMachineRegistryMock();
        $contextMock = $this->getContextMock();

        // assert
        $processingHelper = new ProcessingHelper($apiMock, $entityRepositoryInterfaceMock, $stateMachineRegistryMock);
        $processingHelper->updateTransaction($paynlTransactionMock, $contextMock, $isExchange);
        $this->assertTrue(true);
    }

    public function testStorePaynlTransactionData()
    {
        $entitySearchResultMock = $this->getEntitySearchResultMock(
            $this->getPaynlTransactionMock()
        );

        $entityRepositoryInterfaceMock = $this->getEntityRepositoryInterfaceMock(
            $this->getEntityWrittenContainerEventMock(),
            $entitySearchResultMock
        );

        $transactionMock = $this->getAsyncPaymentTransactionStructMock(
            $this->getOrderEntityMock(),
            $this->getOrderTransactionEntityMock()
        );

        $salesChannelContextMock = $this->getSalesChannelContextMock(
            $this->getCurrencyEntityMock(),
            $this->getShippingMethodEntityMock(),
            $this->getPaymentMethodEntityMock(),
            $this->getCustomerEntityMock(),
            $this->getContextMock()
        );

        $processingHelper = new ProcessingHelper(
            $this->getApiMock($this->getResultTransactionMock()),
            $entityRepositoryInterfaceMock,
            $this->getStateMachineRegistryMock()
        );

        $processingHelper->storePaynlTransactionData(
            $transactionMock,
            $salesChannelContextMock,
            $this->paynlTransactionId,
            $this->exception
        );

        $this->assertTrue(true);
    }

    public function testProcessNotify()
    {
        $apiMock = $this->getApiMock($this->getResultTransactionMock());
        $entitySearchResultMock = $this->getEntitySearchResultMock($this->getPaynlTransactionMock());
        $entityRepositoryInterfaceMock = $this->getEntityRepositoryInterfaceMock(
            $this->getEntityWrittenContainerEventMock(),
            $entitySearchResultMock
        );

        $processingHelper = new ProcessingHelper(
            $apiMock,
            $entityRepositoryInterfaceMock,
            $this->getStateMachineRegistryMock()
        );

        $processingHelper->processNotify($this->paynlTransactionId);

        $this->assertTrue(true);
    }

    private function getApiMock($transactionMock)
    {
        $apiMock = \Mockery::mock(Api::class);
        $apiMock->shouldReceive('getTransaction')
            ->andReturn($transactionMock);
        $apiMock->shouldReceive('getPaynlPaymentMethodId')
            ->andReturn($this->paynlPaymentMethodId);

        return $apiMock;
    }

    private function getResultTransactionMock()
    {
        $transactionMock = \Mockery::mock(ResultTransaction::class);
        $transactionMock->shouldReceive('isBeingVerified')
            ->andReturn(false);
        $transactionMock->shouldReceive('isPending')
            ->andReturn(false);
        $transactionMock->shouldReceive('isPartiallyRefunded')
            ->andReturn(false);
        $transactionMock->shouldReceive('isRefunded')
            ->andReturn(false);
        $transactionMock->shouldReceive('isAuthorized')
            ->andReturn(false);
        $transactionMock->shouldReceive('isPaid')
            ->andReturn(true);
        $transactionMock->shouldReceive('isCanceled')
            ->andReturn(false);
        $transactionMock->shouldReceive('getPaynlPaymentMethodId')
            ->andReturn($this->paynlPaymentMethodId);

        return $transactionMock;
    }

    private function getEntityRepositoryInterfaceMock($entityWrittenContainerEventMock, $entitySearchResultMock)
    {
        $entityRepositoryInterfaceMock = \Mockery::mock(EntityRepositoryInterface::class);
        $entityRepositoryInterfaceMock->shouldReceive('update')
            ->andReturn($entityWrittenContainerEventMock);
        $entityRepositoryInterfaceMock->shouldReceive('create')
            ->andReturn($entityWrittenContainerEventMock);
        $entityRepositoryInterfaceMock->shouldReceive('search')
            ->andReturn($entitySearchResultMock);

        return $entityRepositoryInterfaceMock;
    }

    private function getStateMachineRegistryMock()
    {
        return \Mockery::mock(StateMachineRegistry::class);
    }

    private function getEntitySearchResultMock($paynlTransactionMock)
    {
        $entitySearchResultMock = \Mockery::mock(EntitySearchResult::class);
        $entitySearchResultMock->shouldReceive('first')
            ->andReturn($paynlTransactionMock);

        return $entitySearchResultMock;
    }

    private function getEntityWrittenContainerEventMock()
    {
        return \Mockery::mock(EntityWrittenContainerEvent::class);
    }

    private function getPaynlTransactionMock()
    {
        $paynlTransactionMock = \Mockery::mock(PaynlTransactionEntity::class);
        $paynlTransactionMock->shouldReceive('getPaynlTransactionId')
            ->andReturn($this->paynlTransactionId);
        $paynlTransactionMock
            ->shouldReceive('get')
            ->with('orderTransactionId')
            ->andReturn($this->orderTransactionId);

        return $paynlTransactionMock;
    }

    private function getContextMock()
    {
        return \Mockery::mock(Context::class);
    }

    private function getAsyncPaymentTransactionStructMock($orderEntityMock, $orderTransactionEntityMock)
    {
        $asyncPaymentTransactionStruct = \Mockery::mock(AsyncPaymentTransactionStruct::class);
        $asyncPaymentTransactionStruct->shouldReceive('getOrder')
            ->andReturn($orderEntityMock);
        $asyncPaymentTransactionStruct->shouldReceive('getOrderTransaction')
            ->andReturn($orderTransactionEntityMock);

        return $asyncPaymentTransactionStruct;
    }

    private function getOrderEntityMock()
    {
        $orderEntity = \Mockery::mock(OrderEntity::class);
        $orderEntity->shouldReceive('getId')
            ->andReturn($this->orderId);
        $orderEntity->shouldReceive('getAmountTotal')
            ->andReturn($this->amountTotal);
        $orderEntity->shouldReceive('getStateId')
            ->andReturn($this->stateId);

        return $orderEntity;
    }

    private function getOrderTransactionEntityMock()
    {
        $orderTransactionEntity = \Mockery::mock(OrderTransactionEntity::class);
        $orderTransactionEntity->shouldReceive('getId')
            ->andReturn($this->orderTransactionId);

        return $orderTransactionEntity;
    }

    private function getSalesChannelContextMock(
        $currencyEntityMock,
        $shippingMethodEntityMock,
        $paymentMethodEntityMock,
        $customerEntityMock,
        $contextMock
    ) {
        $salesChannelContextMock = \Mockery::mock(SalesChannelContext::class);
        $salesChannelContextMock->shouldReceive('getCurrency')
            ->andReturn($currencyEntityMock);
        $salesChannelContextMock->shouldReceive('getShippingMethod')
            ->andReturn($shippingMethodEntityMock);
        $salesChannelContextMock->shouldReceive('getPaymentMethod')
            ->andReturn($paymentMethodEntityMock);
        $salesChannelContextMock->shouldReceive('getCustomer')
            ->andReturn($customerEntityMock);
        $salesChannelContextMock->shouldReceive('getContext')
            ->andReturn($contextMock);

        return $salesChannelContextMock;
    }

    private function getShippingMethodEntityMock()
    {
        $shippingMethodEntityMock = \Mockery::mock(ShippingMethodEntity::class);
        $shippingMethodEntityMock->shouldReceive('getId')
            ->andReturn($this->shippingMethodId);

        return $shippingMethodEntityMock;
    }

    private function getCurrencyEntityMock()
    {
        $currencyEntityMock = \Mockery::mock(CurrencyEntity::class);
        $currencyEntityMock->shouldReceive('getIsoCode')
            ->andReturn($this->currencyIsoCode);

        return $currencyEntityMock;
    }

    private function getPaymentMethodEntityMock()
    {
        $paymentMethodEntityMock = \Mockery::mock(PaymentMethodEntity::class);
        $paymentMethodEntityMock->shouldReceive('getId')
            ->andReturn($this->paynlPaymentMethodId);

        return $paymentMethodEntityMock;
    }

    private function getCustomerEntityMock()
    {
        $customerEntityMock = \Mockery::mock(CustomerEntity::class);
        $customerEntityMock->shouldReceive('getId')
            ->andReturn($this->customerId);

        return $customerEntityMock;
    }
}
