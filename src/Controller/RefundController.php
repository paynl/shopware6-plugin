<?php declare(strict_types=1);

namespace PaynlPayment\Controller;

use PaynlPayment\Components\Api;
use PaynlPayment\Components\Config;
use PaynlPayment\Entity\PaynlTransactionEntity;
use PaynlPayment\Helper\ProcessingHelper;
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

    /** @var ProcessingHelper */
    private $processingHelper;

    public function __construct(
        Api $paynlApi,
        Config $paynlConfig,
        EntityRepositoryInterface $transactionRepository,
        EntityRepositoryInterface $productRepository,
        ProcessingHelper $processingHelper
    ) {
        $this->paynlApi = $paynlApi;
        $this->paynlConfig = $paynlConfig;
        $this->transactionRepository = $transactionRepository;
        $this->productRepository = $productRepository;
        $this->processingHelper = $processingHelper;
    }

    /**
     * @Route("/api/v1/paynl/get-refund-data", name="api.PaynlPayment.getRefundData", methods={"GET"})
     */
    public function getRefundData(Request $request): JsonResponse
    {
        $paynlTransactionId = $request->get('transactionId');
        try {
            $apiTransaction = $this->paynlApi->getTransaction($paynlTransactionId);
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
     * @Route("/api/v{version}/paynl/refund", defaults={"csrf_protected"=false}, name="frontend.PaynlPayment.refund", methods={"POST"})
     */
    public function refund(Request $request): JsonResponse
    {
        $post = $request->request->all();
        $paynlPaymentId = $post['transactionId'];
        $amount = (string)$post['amount'];
        $description = $post['description'];
        $products = $post['products'];
        $messages = [];
        try {
            // TODO: need newer version of PAYNL/SDK
            $this->paynlApi->refund($paynlPaymentId, $amount, $description);
            $this->restock($products);

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('paynlTransactionId', $paynlPaymentId));
            $context = Context::createDefaultContext();
            /** @var PaynlTransactionEntity $transactionEntity */
            $transactionEntity = $this->transactionRepository->search($criteria, $context)->first();
            $this->processingHelper->updateTransaction($transactionEntity, $context, false);
            $messages[] = [
                'type' => 'success',
                'content' => sprintf('Refund successful %s', (!empty($description) ? "($description)" : ''))
            ];
        } catch (\Throwable $e) {
            $messages[] = ['type' => 'danger', 'content' => $e->getMessage()];
        }

        return new JsonResponse($messages);
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
}
