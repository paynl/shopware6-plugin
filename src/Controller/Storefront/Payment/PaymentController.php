<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\Payment;

use PaynlPayment\Shopware6\Entity\PaynlTransactionEntity;
use PaynlPayment\Shopware6\Exceptions\PaynlTransactionException;
use PaynlPayment\Shopware6\Repository\OrderTransaction\OrderTransactionRepositoryInterface;
use PaynlPayment\Shopware6\Repository\PaynlTransactions\PaynlTransactionsRepositoryInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Checkout\Payment\Controller\PaymentController as ShopwarePaymentController;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront'], 'csrf_protected' => false, 'auth_required' => false, 'auth_enabled' => false])]
class PaymentController extends AbstractController
{
    private const PAY_CUSTOM_FIELD = 'paynl';
    private const TRANSACTION_FINISH_URL = 'transactionFinishUrl';

    private ShopwarePaymentController $paymentController;

    private LoggerInterface $logger;

    private OrderTransactionRepositoryInterface $orderTransactionRepository;

    private PaynlTransactionsRepositoryInterface $paynlTransactionRepository;

    public function __construct(
        ShopwarePaymentController $paymentController,
        LoggerInterface $logger,
        OrderTransactionRepositoryInterface $orderTransactionRepository,
        PaynlTransactionsRepositoryInterface $paynlTransactionRepository
    ) {
        $this->paymentController = $paymentController;
        $this->logger = $logger;
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->paynlTransactionRepository = $paynlTransactionRepository;
    }

    #[Route('/PaynlPayment/finalize-transaction', name: 'frontend.PaynlPayment.finalize-transaction', options: ['seo' => false], methods: ['GET'])]
    public function finalizeTransaction(Request $request, SalesChannelContext $salesChannelContext): RedirectResponse
    {
        $payOrderId = (string) $request->query->get('id');

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
            throw PaynlTransactionException::notFoundByPayTransactionError($payOrderId);
        }
        $order = $orderTransaction->getOrder();

        if ($order === null) {
            throw PaynlTransactionException::notFoundByPayTransactionError($orderTransaction->getId());
        }

        $orderId = $order->getId();

        try {
            $finalizeTransactionResponse = $this->paymentController->finalizeTransaction($request);

            if ($finalizeTransactionResponse instanceof RedirectResponse) {
                $this->saveOrderTransactionFinishUrl(
                    $orderTransaction,
                    $finalizeTransactionResponse->getTargetUrl(),
                    $salesChannelContext
                );

                return $finalizeTransactionResponse;
            }
        } catch (HttpException $httpException) {
            $this->logger->error(
                '{message}. Redirecting to confirm page.',
                ['message' => $httpException->getMessage(), 'error' => $httpException]
            );
        }

        if ($this->getOrderTransactionFinishUrl($orderTransaction)) {
            return $this->redirect($this->getOrderTransactionFinishUrl($orderTransaction));
        }

        return $this->redirectToRoute('frontend.checkout.finish.page', ['orderId' => $orderId]);
    }

    private function saveOrderTransactionFinishUrl(OrderTransactionEntity $orderTransaction, string $finishUrl, SalesChannelContext $salesChannelContext): void
    {
        $currentCustomFields = $orderTransaction->getCustomFields();
        $currentCustomFields[self::PAY_CUSTOM_FIELD][self::TRANSACTION_FINISH_URL] = $finishUrl;

        $this->orderTransactionRepository->update([[
            'id' => $orderTransaction->getId(),
            'customFields' => $currentCustomFields
        ]], $salesChannelContext->getContext());
    }

    private function getOrderTransactionFinishUrl(OrderTransactionEntity $orderTransaction): string
    {
        return (string)($orderTransaction->getCustomFieldsValue(self::PAY_CUSTOM_FIELD)[self::TRANSACTION_FINISH_URL] ?? '');
    }
}
