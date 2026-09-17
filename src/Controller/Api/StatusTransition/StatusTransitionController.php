<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Api\StatusTransition;

use Exception;
use PaynlPayment\Shopware6\Entity\PaynlTransactionEntity;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => true, 'auth_enabled' => true])]
class StatusTransitionController extends AbstractController
{
    private LoggerInterface $logger;
    private ProcessingHelper $processingHelper;
    private EntityRepository $paynlTransactionRepository;

    public function __construct(
        LoggerInterface $logger,
        ProcessingHelper $processingHelper,
        EntityRepository $paynlTransactionRepository
    ) {
        $this->logger = $logger;
        $this->processingHelper = $processingHelper;
        $this->paynlTransactionRepository = $paynlTransactionRepository;
    }

    #[Route('/api/paynl/change-transaction-status', name: 'api.PaynlPayment.changeTransactionStatus', methods: ['POST'])]
    public function changeTransactionStatus(Request $request, Context $context): JsonResponse
    {
        $orderTransactionId = $request->request->get('transactionId', '');
        if (empty($orderTransactionId)) {
            return new JsonResponse(['error' => 'Transaction ID required'], Response::HTTP_BAD_REQUEST);
        }

        $currentActionName = $request->request->get('currentActionName', '');
        try {
            /** @var PaynlTransactionEntity $paynlTransaction */
            $paynlTransaction = $this->paynlTransactionRepository
                ->search(
                    (new Criteria())
                        ->addFilter(new EqualsFilter('orderTransactionId', $orderTransactionId))
                        ->addAssociation('order'),
                    $context
                )
                ->first();

            if (empty($paynlTransaction) || empty($paynlTransaction->getOrder())) {
                return new JsonResponse(['error' => 'Transaction not found'], Response::HTTP_NOT_FOUND);
            }

            $salesChannelId = $paynlTransaction->getOrder()->getSalesChannelId();

            if ($paynlTransaction instanceof PaynlTransactionEntity) {
                $this->processingHelper->processChangePayNLStatus(
                    $paynlTransaction->getId(),
                    $paynlTransaction->getPaynlTransactionId(),
                    $currentActionName,
                    $salesChannelId,
                    $context
                );
            }

            return new JsonResponse(['success' => true], Response::HTTP_OK);
        } catch (Exception $exception) {
            $this->logger->error('Error on changing transaction status.', [
                'exception' => $exception,
                'transactionId' => $orderTransactionId,
                'actionName' => $currentActionName,
                'trace' => $exception->getTraceAsString()
            ]);

            return new JsonResponse(['error' => 'Unable to change transaction status'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
