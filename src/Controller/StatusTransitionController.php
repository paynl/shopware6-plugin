<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller;

use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Entity\PaynlTransactionEntity;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Paynl\Error;

/**
 * @RouteScope(scopes={"api"})
 */
class StatusTransitionController extends AbstractController
{
    private $paynlApi;
    private $paynlConfig;
    private $processingHelper;
    private $paynlTransactionRepository;

    public function __construct(
        Api $paynlApi,
        Config $paynlConfig,
        ProcessingHelper $processingHelper,
        EntityRepositoryInterface $paynlTransactionRepository
    ) {
        $this->paynlApi = $paynlApi;
        $this->paynlConfig = $paynlConfig;
        $this->processingHelper = $processingHelper;
        $this->paynlTransactionRepository = $paynlTransactionRepository;
    }

    /**
     * @Route("/api/paynl/change-transaction-status",
     *     name="api.PaynlPayment.changeTransactionStatus",
     *     methods={"POST"}
     *     )
     */
    public function changeTransactionStatus(Request $request): JsonResponse
    {
        $orderTransactionId = $request->get('transactionId', '');
        $currentActionName = $request->get('currentActionName', '');
        try {
            /** @var PaynlTransactionEntity $paynlTransaction */
            $paynlTransaction = $this->paynlTransactionRepository
                ->search(
                    (new Criteria())->addFilter(new EqualsFilter('orderTransactionId', $orderTransactionId)),
                    Context::createDefaultContext()
                )
                ->first();

            if ($paynlTransaction instanceof PaynlTransactionEntity) {
                $this->processingHelper->processChangePaynlStatus(
                    $paynlTransaction->getId(),
                    $paynlTransaction->getPaynlTransactionId(),
                    $currentActionName
                );
            }

            return new JsonResponse($request->request->all());
        } catch (Error\Api $exception) {
            return new JsonResponse([
                'errorMessage' => $exception->getMessage()
            ], 400);
        }
    }
}
