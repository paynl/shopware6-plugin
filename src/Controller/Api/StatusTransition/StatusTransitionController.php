<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Api\StatusTransition;

use Exception;
use PaynlPayment\Shopware6\Entity\PaynlTransactionEntity;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => true, 'auth_enabled' => true])]
class StatusTransitionController extends AbstractController
{
    /** Privileges granted by the "Orders" editor role, required for the core order transaction transition. */
    private const PRIVILEGE_ORDER_UPDATE = 'order:update';
    private const PRIVILEGE_STATE_TRANSITION = 'state_machine_history:create';

    /**
     * Transition actions synced to PAY., mapped to the order transaction state the core
     * transition must already have reached. Other actions are not synced.
     */
    private const SYNCED_ACTION_TARGET_STATES = [
        StateMachineTransitionActions::ACTION_PAID => OrderTransactionStates::STATE_PAID,
        StateMachineTransitionActions::ACTION_CANCEL => OrderTransactionStates::STATE_CANCELLED,
    ];

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

    #[Route(
        '/api/paynl/change-transaction-status',
        name: 'api.PaynlPayment.changeTransactionStatus',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => [self::PRIVILEGE_ORDER_UPDATE, self::PRIVILEGE_STATE_TRANSITION]],
        methods: ['POST']
    )]
    public function changeTransactionStatus(Request $request, Context $context): JsonResponse
    {
        $orderTransactionId = $request->request->get('transactionId', '');
        if (empty($orderTransactionId)) {
            return new JsonResponse(['error' => 'Transaction ID required'], Response::HTTP_BAD_REQUEST);
        }

        $currentActionName = (string) $request->request->get('currentActionName', '');
        if (!isset(self::SYNCED_ACTION_TARGET_STATES[$currentActionName])) {
            return new JsonResponse(['success' => true], Response::HTTP_OK);
        }

        try {
            $paynlTransaction = $this->paynlTransactionRepository
                ->search(
                    (new Criteria())
                        ->addFilter(new EqualsFilter('orderTransactionId', $orderTransactionId))
                        ->addAssociation('order')
                        ->addAssociation('orderTransaction.stateMachineState'),
                    $context
                )
                ->first();

            if (!$paynlTransaction instanceof PaynlTransactionEntity || empty($paynlTransaction->getOrder())) {
                return new JsonResponse(['error' => 'Transaction not found'], Response::HTTP_NOT_FOUND);
            }

            // PAY. may only follow a transition that Shopware has already completed.
            $currentState = $paynlTransaction->getOrderTransaction()->getStateMachineState()?->getTechnicalName();
            if ($currentState !== self::SYNCED_ACTION_TARGET_STATES[$currentActionName]) {
                return new JsonResponse(['error' => 'Transaction state does not match action'], Response::HTTP_CONFLICT);
            }

            $this->processingHelper->processChangePayNLStatus(
                $paynlTransaction->getId(),
                $paynlTransaction->getPaynlTransactionId(),
                $currentActionName,
                $paynlTransaction->getOrder()->getSalesChannelId(),
                $context
            );

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
