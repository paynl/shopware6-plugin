<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\PaynlTransaction;

use PaynlPayment\Shopware6\Entity\PaynlTransactionEntity;
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
}
