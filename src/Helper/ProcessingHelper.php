<?php declare(strict_types=1);

namespace PaynlPayment\Helper;

use Exception;
use Paynl\Result\Transaction\Transaction as ResultTransaction;
use PaynlPayment\Components\Api;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProcessingHelper
{
    /** @var Api */
    private $paynlApi;
    /** @var EntityRepositoryInterface */
    private $paynlTransactionRepository;

    public function __construct(Api $api, EntityRepositoryInterface $paynlTransactionRepository)
    {
        $this->paynlApi = $api;
        $this->paynlTransactionRepository = $paynlTransactionRepository;
    }

    public function storePaynlTransactionData(
        AsyncPaymentTransactionStruct $transaction,
        SalesChannelContext $salesChannelContext,
        string $paynlTransactionId,
        ?Exception $exception = null
    ): void {
        $shopwarePaymentMethodId = $salesChannelContext->getPaymentMethod()->getId();
        /** @var CustomerEntity $customer */
        $customer = $salesChannelContext->getCustomer();
        $transactionData = [
            'paynlTransactionId' => $paynlTransactionId,
            'customerId' => $customer->getId(),
            'orderId' => $transaction->getOrder()->getId(),
            'paymentId' => $this->paynlApi->getPaynlPaymentMethodId($shopwarePaymentMethodId),
            'amount' => $transaction->getOrder()->getAmountTotal(),
            'currency' => $salesChannelContext->getCurrency()->getIsoCode(),
            'exception' => (string)$exception,
        ];
        $this->paynlTransactionRepository->create([$transactionData], $salesChannelContext->getContext());
    }

    public function findTransactionByOrderId(string $orderId, Context $context)
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('orderId', $orderId));
        return $this->paynlTransactionRepository->search($criteria, $context)->first();
    }

    public function getApiTransaction(string $transactionId): ResultTransaction
    {
        return $this->paynlApi->getTransaction($transactionId);
    }
}
