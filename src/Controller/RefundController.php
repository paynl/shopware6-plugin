<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller;

use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Entity\PaynlTransactionEntity;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Paynl\Error;

/**
 * @RouteScope(scopes={"api"})
 */
class RefundController extends AbstractController
{
    private $paynlApi;
    private $paynlConfig;
    private $transactionRepository;
    private $productRepository;
    private $paynlTransactionRepository;

    /** @var ProcessingHelper */
    private $processingHelper;

    public function __construct(
        Api $paynlApi,
        Config $paynlConfig,
        EntityRepositoryInterface $transactionRepository,
        EntityRepositoryInterface $productRepository,
        ProcessingHelper $processingHelper,
        EntityRepositoryInterface $paynlTransactionRepository
    ) {
        $this->paynlApi = $paynlApi;
        $this->paynlConfig = $paynlConfig;
        $this->transactionRepository = $transactionRepository;
        $this->productRepository = $productRepository;
        $this->processingHelper = $processingHelper;
        $this->paynlTransactionRepository = $paynlTransactionRepository;
    }

    /**
     * @Route("/api/paynl/get-refund-data", name="api.PaynlPayment.getRefundDataSW64", methods={"GET"})
     */
    public function getRefundDataSW64(Request $request): JsonResponse
    {
        return $this->getRefundDataResponse($request);
    }

    /**
     * @Route("/api/v{version}/paynl/get-refund-data", name="api.PaynlPayment.getRefundData", methods={"GET"})
     */
    public function getRefundData(Request $request): JsonResponse
    {
        return $this->getRefundDataResponse($request);
    }

    /**
     * @Route("/api/paynl/refund", name="frontend.PaynlPayment.refundSW64", methods={"POST"})
     */
    public function refundSW64(Request $request): JsonResponse
    {
        return $this->getRefundResponse($request);
    }

    /**
     * @Route("/api/v{version}/paynl/refund", name="frontend.PaynlPayment.refund", methods={"POST"})
     */
    public function refund(Request $request): JsonResponse
    {
        return $this->getRefundResponse($request);
    }

    private function getRefundResponse(Request $request): JsonResponse
    {
        $post = $request->request->all();
        $paynlTransactionId = $post['transactionId'];
        $amount = (string)$post['amount'];
        $description = $post['description'];
        $products = $post['products'];
        $messages = [];

        $paynlTransaction = $this->getPaynlTransactionEntityByPaynlTransactionId($paynlTransactionId);
        $salesChannelId = $paynlTransaction->getOrder()->getSalesChannelId();

        try {
            // TODO: need newer version of PAYNL/SDK
            $this->paynlApi->refund($paynlTransactionId, $amount, $salesChannelId, $description);
            $this->restock($products);

            $this->processingHelper->refundActionUpdateTransactionByTransactionId($paynlTransactionId);
            $messages[] = [
                'type' => 'success',
                'content' => sprintf('Refund successful %s', (!empty($description) ? "($description)" : ''))
            ];
        } catch (\Throwable $e) {
            $messages[] = ['type' => 'danger', 'content' => $e->getMessage()];
        }

        return new JsonResponse($messages);
    }

    private function getRefundDataResponse(Request $request): JsonResponse
    {
        $paynlTransactionId = $request->get('transactionId');
        $paynlTransaction = $this->getPaynlTransactionEntityByPaynlTransactionId($paynlTransactionId);
        $salesChannelId = $paynlTransaction->getOrder()->getSalesChannelId();

        try {
            $apiTransaction = $this->paynlApi->getTransaction($paynlTransactionId, $salesChannelId);
            $refundedAmount = $apiTransaction->getRefundedAmount();
            $availableForRefund = $apiTransaction->getAmount() - $refundedAmount;

            return new JsonResponse([
                'refundedAmount' => $refundedAmount,
                'availableForRefund' => $availableForRefund
            ]);
        } catch (Error\Api $exception) {
            return new JsonResponse([
                'errorMessage' => $exception->getMessage()
            ], 400);
        }
    }

    /**
     * @param mixed[] $products
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    private function restock(array $products = []): void
    {
        $data = [];
        $context = Context::createDefaultContext();
        foreach ($products as $product) {
            if (isset($product['rstk']) && $product['rstk'] == true) {
                $criteria = new Criteria();
                $criteria->addFilter(new EqualsFilter('product.id', $product['identifier']));
                /** @var ProductEntity $productEntity */
                $productEntity = $this->productRepository->search($criteria, $context)->first();
                $newStock = $productEntity->getStock() + $product['qnt'];
                $data[] = [
                    'id' => $product['identifier'],
                    'stock' => $newStock
                ];
            }
        }

        if (!empty($data)) {
            $this->productRepository->update($data, $context);
        }
    }

    private function getPaynlTransactionEntityByPaynlTransactionId(string $paynlTransactionId): PaynlTransactionEntity
    {
        $criteria = (new Criteria());
        $criteria->addFilter(new EqualsFilter('paynlTransactionId', $paynlTransactionId));
        $criteria->addAssociation('order');

        return $this->paynlTransactionRepository->search($criteria, Context::createDefaultContext())->first();
    }
}
