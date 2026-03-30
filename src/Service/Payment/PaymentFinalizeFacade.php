<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service\Payment;

use PaynlPayment\Shopware6\Entity\PaynlTransactionEntity;
use PaynlPayment\Shopware6\Exceptions\PaynlTransactionException;
use PaynlPayment\Shopware6\Repository\OrderTransaction\OrderTransactionRepositoryInterface;
use PaynlPayment\Shopware6\Repository\PaynlTransactions\PaynlTransactionsRepositoryInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\Controller\PaymentController as ShopwarePaymentController;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\RouterInterface;

class PaymentFinalizeFacade
{
    private const PAY_CUSTOM_FIELD = 'paynl';
    private const TRANSACTION_FINISH_URL = 'transactionFinishUrl';

    private ShopwarePaymentController $paymentController;

    private LoggerInterface $logger;

    private OrderTransactionRepositoryInterface $orderTransactionRepository;

    private PaynlTransactionsRepositoryInterface $paynlTransactionRepository;

    private RouterInterface $router;

    public function __construct(
        ShopwarePaymentController $paymentController,
        LoggerInterface $logger,
        OrderTransactionRepositoryInterface $orderTransactionRepository,
        PaynlTransactionsRepositoryInterface $paynlTransactionRepository,
        RouterInterface $router
    ) {
        $this->paymentController = $paymentController;
        $this->logger = $logger;
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->paynlTransactionRepository = $paynlTransactionRepository;
        $this->router = $router;
    }

    public function finalizeTransaction(Request $request, Context $context): FinalizeResult
    {
        $payOrderId = (string) $request->query->get('id');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('paynlTransactionId', $payOrderId));
        $criteria->addAssociation('order');
        $criteria->addAssociation('orderTransaction.stateMachineState');
        $criteria->addAssociation('orderTransaction.order');

        /** @var PaynlTransactionEntity|null $payTransaction */
        $payTransaction = $this->paynlTransactionRepository->search($criteria, $context)->first();

        if ($payTransaction === null) {
            throw PaynlTransactionException::notFoundByPayTransactionError($payOrderId);
        }

        $orderTransaction = $payTransaction->getOrderTransaction();
        if ($orderTransaction === null) {
            throw PaynlTransactionException::notFoundByPayTransactionError($payOrderId);
        }

        $order = $orderTransaction->getOrder();
        if ($order === null) {
            throw PaynlTransactionException::notFoundByPayTransactionError($orderTransaction->getId());
        }

        try {
            $response = $this->paymentController->finalizeTransaction($request);
            if ($response instanceof RedirectResponse) {
                return new FinalizeResult($response, $orderTransaction);
            }
        } catch (HttpException $e) {
            $this->logger->error('{message}. Redirecting to confirm page.', [
                'message' => $e->getMessage(),
                'error' => $e,
            ]);
        }

        $finishUrl = $this->getOrderTransactionFinishUrl($orderTransaction);
        if ($finishUrl !== '') {
            return new FinalizeResult(new RedirectResponse($finishUrl), $orderTransaction);
        }

        $redirectUrl = $this->router->generate(
            'frontend.checkout.finish.page',
            ['orderId' => $order->getId()],
            RouterInterface::ABSOLUTE_URL
        );

        return new FinalizeResult(new RedirectResponse($redirectUrl), $orderTransaction);
    }

    private function getOrderTransactionFinishUrl(OrderTransactionEntity $orderTransaction): string
    {
        $customFields = $orderTransaction->getCustomFields() ?? [];
        $paynl = $customFields[self::PAY_CUSTOM_FIELD] ?? [];

        return (string) ($paynl[self::TRANSACTION_FINISH_URL] ?? '');
    }
}
