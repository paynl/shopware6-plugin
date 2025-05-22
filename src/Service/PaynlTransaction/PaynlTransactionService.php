<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PaynlTransaction;

use PaynlPayment\Shopware6\Entity\PaynlTransactionEntity;
use PaynlPayment\Shopware6\Exceptions\PaynlTransactionException;
use PaynlPayment\Shopware6\Repository\PaynlTransactions\PaynlTransactionsRepositoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class PaynlTransactionService
{
    /** @var PaynlTransactionsRepositoryInterface */
    protected $paynlTransactionsRepository;

    public function __construct(PaynlTransactionsRepositoryInterface $paynlTransactionsRepository)
    {
        $this->paynlTransactionsRepository = $paynlTransactionsRepository;
    }

    public function getPayTransactionByOrderId(string $orderId, Context $context): ?PaynlTransactionEntity
    {
        /** @var PaynlTransactionEntity $payTransaction */
        $payTransaction = $this->paynlTransactionsRepository
            ->search(
                (new Criteria())->addFilter(new EqualsFilter('orderId', $orderId)),
                $context
            )
            ->first();

        if ($payTransaction instanceof PaynlTransactionEntity) {
            return $payTransaction;
        }

        return null;
    }

    /** @throws PaynlTransactionException */
    public function getOrderNumberByPayTransactionId(string $payTransactionId, Context $context): string
    {
        $criteria = (new Criteria());
        $criteria->addFilter(new EqualsFilter('paynlTransactionId', $payTransactionId));
        $criteria->addAssociation('order');
        $criteria->addAssociation('orderTransaction.order');

        $payTransaction = $this->paynlTransactionsRepository->search($criteria, $context)->first();

        if ($payTransaction instanceof PaynlTransactionEntity) {
            $orderNumber = $payTransaction->getOrder() ? $payTransaction->getOrder()->getOrderNumber() : null;

            if (empty($orderNumber)) {
                throw PaynlTransactionException::notFoundByPayTransactionError($payTransactionId);
            }

            return (string) $orderNumber;
        }

        throw PaynlTransactionException::notFoundByPayTransactionError($payTransactionId);
    }
}
