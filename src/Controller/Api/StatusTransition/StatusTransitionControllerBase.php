<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Api\StatusTransition;

use Paynl\Error;
use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Entity\PaynlTransactionEntity;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class StatusTransitionControllerBase extends AbstractController
{
    private $paynlApi;
    private $paynlConfig;
    private $processingHelper;
    private $paynlTransactionRepository;

    public function __construct(
        Api $paynlApi,
        Config $paynlConfig,
        ProcessingHelper $processingHelper,
        EntityRepository $paynlTransactionRepository
    ) {
        $this->paynlApi = $paynlApi;
        $this->paynlConfig = $paynlConfig;
        $this->processingHelper = $processingHelper;
        $this->paynlTransactionRepository = $paynlTransactionRepository;
    }

    protected function getChangeTransactionStatusResponse(Request $request): JsonResponse
    {
        $orderTransactionId = $request->request->get('transactionId', '');
        $currentActionName = $request->request->get('currentActionName', '');
        try {
            /** @var PaynlTransactionEntity $paynlTransaction */
            $paynlTransaction = $this->paynlTransactionRepository
                ->search(
                    (new Criteria())
                        ->addFilter(new EqualsFilter('orderTransactionId', $orderTransactionId))
                        ->addAssociation('order'),
                    Context::createDefaultContext()
                )
                ->first();

            if (empty($paynlTransaction) || empty($paynlTransaction->getOrder())) {
                return new JsonResponse($request->request->all());
            }

            $salesChannelId = $paynlTransaction->getOrder()->getSalesChannelId();

            if ($paynlTransaction instanceof PaynlTransactionEntity) {
                $this->processingHelper->processChangePaynlStatus(
                    $paynlTransaction->getId(),
                    $paynlTransaction->getPaynlTransactionId(),
                    $currentActionName,
                    $salesChannelId
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
