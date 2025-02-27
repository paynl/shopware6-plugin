<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Api\Refund;

use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Entity\PaynlTransactionEntity;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class RefundControllerBase extends AbstractController
{
    private Api $payAPI;
    private ProcessingHelper $processingHelper;
    private LoggerInterface $logger;
    private EntityRepository $productRepository;
    private EntityRepository $payTransactionRepository;

    public function __construct(
        Api $payAPI,
        ProcessingHelper $processingHelper,
        LoggerInterface $logger,
        EntityRepository $productRepository,
        EntityRepository $payTransactionRepository
    ) {
        $this->payAPI = $payAPI;
        $this->processingHelper = $processingHelper;
        $this->logger = $logger;
        $this->productRepository = $productRepository;
        $this->payTransactionRepository = $payTransactionRepository;
    }

    protected function getRefundDataResponse(Request $request): JsonResponse
    {
        $paynlTransactionId = $request->query->get('transactionId');
        $paynlTransaction = $this->getPayTransactionEntityByPayTransactionId($paynlTransactionId);
        $salesChannelId = $paynlTransaction->getOrder()->getSalesChannelId();

        try {
            $this->logger->info('Refund data for transaction ' . $paynlTransactionId);

            $apiTransaction = $this->payAPI->getTransaction($paynlTransactionId, $salesChannelId);
            $refundedAmount = $apiTransaction->getRefundedAmount();
            $availableForRefund = $apiTransaction->getAmount() - $refundedAmount;

            return new JsonResponse([
                'refundedAmount' => $refundedAmount,
                'availableForRefund' => $availableForRefund
            ]);
        } catch (\Paynl\Error\Api $exception) {
            $this->logger->error('Error on getting refund data for transaction ' . $paynlTransactionId, [
                'exception' => $exception
            ]);

            return new JsonResponse([
                'errorMessage' => $exception->getMessage()
            ], 400);
        }
    }

    protected function getRefundResponse(Request $request): JsonResponse
    {
        $post = $request->request->all();
        $paynlTransactionId = $post['transactionId'];
        $amount = (float) $post['amount'];
        $description = $post['description'];
        $products = $post['products'];
        $messages = [];

        $paynlTransaction = $this->getPayTransactionEntityByPayTransactionId($paynlTransactionId);
        $salesChannelId = $paynlTransaction->getOrder()->getSalesChannelId();
        $salesChannel = $paynlTransaction->getOrder()->getSalesChannel();

        try {
            $this->logger->info('Start refunding for transaction ' . $paynlTransactionId, [
                'transactionId' => $paynlTransactionId,
                'amount' => $amount,
                'salesChannel' => $salesChannel ? $salesChannel->getName() : ''
            ]);

            $this->payAPI->refund($paynlTransactionId, $amount, $salesChannelId, $description);
            $this->restock($products);

            $this->processingHelper->refundActionUpdateTransactionByTransactionId($paynlTransactionId);
            $messages[] = [
                'type' => 'success',
                'content' => sprintf('Refund successful %s', (!empty($description) ? "($description)" : ''))
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Error on refunding transaction ' . $paynlTransactionId, [
                'exception' => $e,
                'amount' => $amount
            ]);

            $messages[] = ['type' => 'danger', 'content' => $e->getMessage()];
        }

        return new JsonResponse($messages);
    }

    /** @throws InconsistentCriteriaIdsException */
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

    private function getPayTransactionEntityByPayTransactionId(string $payTransactionId): PaynlTransactionEntity
    {
        $criteria = (new Criteria());
        $criteria->addFilter(new EqualsFilter('paynlTransactionId', $payTransactionId));
        $criteria->addAssociation('order');

        return $this->payTransactionRepository->search($criteria, Context::createDefaultContext())->first();
    }
}
