<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\Payment;

use PaynlPayment\Shopware6\Repository\OrderTransaction\OrderTransactionRepositoryInterface;
use PaynlPayment\Shopware6\Service\Payment\PaymentFinalizeFacade;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront'], 'csrf_protected' => false, 'auth_required' => false, 'auth_enabled' => false])]
class PaymentController extends AbstractController
{
    private const PAY_CUSTOM_FIELD = 'paynl';
    private const TRANSACTION_FINISH_URL = 'transactionFinishUrl';

    private OrderTransactionRepositoryInterface $orderTransactionRepository;

    private PaymentFinalizeFacade $finalizeFacade;

    public function __construct(
        OrderTransactionRepositoryInterface $orderTransactionRepository,
        PaymentFinalizeFacade $finalizeFacade
    ) {
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->finalizeFacade = $finalizeFacade;
    }

    #[Route('/PaynlPayment/finalize-transaction', name: 'frontend.PaynlPayment.finalize-transaction', options: ['seo' => false], methods: ['GET'])]
    public function finalizeTransaction(Request $request, SalesChannelContext $salesChannelContext): RedirectResponse
    {
        $result = $this->finalizeFacade->finalizeTransaction($request, $salesChannelContext->getContext());

        $orderTransaction = $result->getOrderTransaction();
        if ($orderTransaction !== null) {
            $response = $result->getResponse();
            if ($response instanceof RedirectResponse) {
                $this->saveOrderTransactionFinishUrl(
                    $orderTransaction,
                    $response->getTargetUrl(),
                    $salesChannelContext
                );
            }
        }

        return $result->getResponse();
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
