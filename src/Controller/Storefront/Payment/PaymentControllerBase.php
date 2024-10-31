<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\Payment;

use PaynlPayment\Shopware6\Entity\PaynlTransactionEntity;
use PaynlPayment\Shopware6\Repository\PaynlTransactions\PaynlTransactionsRepositoryInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\Controller\PaymentController;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class PaymentControllerBase extends AbstractController
{
    /** @var PaymentController */
    private $paymentController;

    /** @var LoggerInterface */
    private $logger;

    /** @var PaynlTransactionsRepositoryInterface */
    private $paynlTransactionRepository;

    public function __construct(
        PaymentController $paymentController,
        LoggerInterface $logger,
        PaynlTransactionsRepositoryInterface $paynlTransactionRepository
    ) {
        $this->paymentController = $paymentController;
        $this->logger = $logger;
        $this->paynlTransactionRepository = $paynlTransactionRepository;
    }

    protected function finalizeTransactionResponse(Request $request, SalesChannelContext $salesChannelContext): RedirectResponse
    {
        $payOrderId = (string) $request->query->get('orderId');

        $criteria = (new Criteria());
        $criteria->addFilter(new EqualsFilter('paynlTransactionId', $payOrderId));
        $criteria->addAssociation('order');
        $criteria->addAssociation('orderTransaction.stateMachineState');
        $criteria->addAssociation('orderTransaction.order');

        $context = $salesChannelContext->getContext();

        /** @var PaynlTransactionEntity $payTransaction */
        $payTransaction = $this->paynlTransactionRepository->search($criteria, $context)->first();

        /** @var OrderTransactionEntity|null $orderTransaction */
        $orderTransaction = $payTransaction->getOrderTransaction();

        if ($orderTransaction === null) {
            throw PaymentException::invalidTransaction($payOrderId);
        }
        $order = $orderTransaction->getOrder();

        if ($order === null) {
            throw PaymentException::invalidTransaction($orderTransaction->getId());
        }

        $orderId = $order->getId();

        try {
            $this->paymentController->finalizeTransaction($request);
        } catch (PaymentException $paymentException) {
            $this->logger->error(
                '{message}. Redirecting to confirm page.',
                ['message' => $paymentException->getMessage(), 'error' => $paymentException]
            );
        }

        return $this->redirectToRoute('frontend.checkout.finish.page', ['orderId' => $orderId]);
    }
}
