<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Api\Refund;

use PayNL\Sdk\Exception\PayException;
use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Entity\PaynlTransactionEntity;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['api'], 'auth_required' => true, 'auth_enabled' => true])]
class RefundController extends AbstractController
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

    #[Route('/api/paynl/get-refund-data', name: 'api.PaynlPayment.getRefundData', methods: ['GET'])]
    public function getRefundData(Request $request, Context $context): JsonResponse
    {
        $paynlTransactionId = $request->query->get('transactionId');
        if (!is_string($paynlTransactionId) || trim($paynlTransactionId) === '') {
            return new JsonResponse(['errorMessage' => 'paynlValidation.error.missingTransactionId'], 400);
        }
        $paynlTransactionId = trim($paynlTransactionId);

        $paynlTransaction = $this->getPayTransactionEntityByPayTransactionId($paynlTransactionId, $context);
        if ($paynlTransaction === null) {
            return new JsonResponse(['errorMessage' => 'paynlValidation.error.transactionNotFound'], 404);
        }
        $salesChannelId = $paynlTransaction->getOrder()->getSalesChannelId();

        try {
            $this->logger->info('Refund data for transaction ' . $paynlTransactionId);

            $payTransactionStatus = $this->payAPI->getTransactionStatus($paynlTransactionId, $salesChannelId);
            $refundedAmount = $payTransactionStatus->getAmountRefunded();
            $availableForRefund = $payTransactionStatus->getAmount() - $refundedAmount;

            return new JsonResponse([
                'refundedAmount' => $refundedAmount,
                'availableForRefund' => $availableForRefund
            ]);
        } catch (PayException $exception) {
            $this->logger->error('Error on getting refund data for transaction ' . $paynlTransactionId, [
                'exception' => $exception
            ]);

            return new JsonResponse([
                'errorMessage' => $exception->getMessage()
            ], 400);
        }
    }

    #[Route('/api/paynl/refund', name: 'frontend.PaynlPayment.refund', methods: ['POST'])]
    public function refund(Request $request, Context $context): JsonResponse
    {
        $post = $request->request->all();

        if (empty($post['transactionId']) || !isset($post['amount'])) {
            return new JsonResponse([['type' => 'danger', 'content' => 'paynlValidation.error.missingFields']], 400);
        }

        $paynlTransactionId = trim((string) $post['transactionId']);
        $amount = (float) $post['amount'];
        $description = $post['description'] ?? '';
        $products = is_array($post['products'] ?? null) ? $post['products'] : [];

        if ($paynlTransactionId === '' || $amount <= 0) {
            return new JsonResponse([['type' => 'danger', 'content' => 'paynlValidation.error.invalidAmount']], 400);
        }

        $paynlTransaction = $this->getPayTransactionEntityByPayTransactionId($paynlTransactionId, $context);
        if ($paynlTransaction === null) {
            return new JsonResponse([['type' => 'danger', 'content' => 'paynlValidation.error.transactionNotFound']], 404);
        }
        $salesChannelId = $paynlTransaction->getOrder()->getSalesChannelId();
        $salesChannel = $paynlTransaction->getOrder()->getSalesChannel();

        $messages = [];

        try {
            $this->logger->info('Start refunding for transaction ' . $paynlTransactionId, [
                'transactionId' => $paynlTransactionId,
                'amount' => $amount,
                'salesChannel' => $salesChannel ? $salesChannel->getName() : ''
            ]);

            $this->payAPI->refund($paynlTransactionId, $amount, $salesChannelId, $description);
            $order = $paynlTransaction->getOrder();
            if ($order !== null) {
                $this->restock($products, $order, $context);
            }

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

    /**
     * Restocks only products that belong to the refunded order.
     * Quantities are capped to what was ordered.
     *
     * @param array<int, array<string, mixed>> $products Payload from admin (order line items + rstk/qnt)
     *
     * @throws InconsistentCriteriaIdsException
     */
    private function restock(array $products, OrderEntity $order, Context $context): void
    {
        if ($products === []) {
            return;
        }

        $allowedProducts = $this->buildRestockAllowlist($order);
        if ($allowedProducts === []) {
            return;
        }

        $updates = [];

        foreach ($products as $product) {
            if (!is_array($product) || empty($product['rstk'])) {
                continue;
            }

            $productId = $this->resolveRestockProductId($product);
            if ($productId === null || !isset($allowedProducts[$productId])) {
                $this->logger->warning('Refund restock skipped: product not part of order', [
                    'productId' => $productId,
                    'orderId' => $order->getId(),
                ]);
                continue;
            }

            $quantity = (int) ($product['qnt'] ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            $quantity = min($quantity, $allowedProducts[$productId]);
            if ($quantity <= 0) {
                continue;
            }

            $productEntity = $this->findProductById($productId, $context);
            if ($productEntity === null) {
                $this->logger->warning('Refund restock skipped: product not found', [
                    'productId' => $productId,
                    'orderId' => $order->getId(),
                ]);
                continue;
            }

            $updates[] = [
                'id' => $productId,
                'stock' => $productEntity->getStock() + $quantity,
            ];
        }

        if ($updates !== []) {
            $this->productRepository->update($updates, $context);
        }
    }

    /**
     * @return array<string, int> productId => max restockable quantity for this order
     */
    private function buildRestockAllowlist(OrderEntity $order): array
    {
        $allowlist = [];

        foreach ($order->getLineItems() ?? [] as $lineItem) {
            $productId = $lineItem->getProductId();
            if ($productId === null) {
                continue;
            }

            $orderedQty = max(0, $lineItem->getQuantity());
            if ($orderedQty === 0) {
                continue;
            }

            $allowlist[$productId] = isset($allowlist[$productId])
                ? $allowlist[$productId] + $orderedQty
                : $orderedQty;
        }

        return $allowlist;
    }

    /** @param array<string, mixed> $product */
    private function resolveRestockProductId(array $product): ?string
    {
        $productId = $product['productId'] ?? $product['identifier'] ?? null;

        if (!is_string($productId)) {
            return null;
        }

        $productId = trim($productId);

        return $productId !== '' ? $productId : null;
    }

    private function findProductById(string $productId, Context $context): ?ProductEntity
    {
        $criteria = new Criteria([$productId]);

        $product = $this->productRepository->search($criteria, $context)->get($productId);

        return $product instanceof ProductEntity ? $product : null;
    }

    private function getPayTransactionEntityByPayTransactionId(
        string $payTransactionId,
        Context $context
    ): ?PaynlTransactionEntity {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('paynlTransactionId', $payTransactionId))
            ->addAssociation('order')
            ->addAssociation('order.lineItems');
        $result = $this->payTransactionRepository->search($criteria, $context)->first();

        return $result instanceof PaynlTransactionEntity ? $result : null;
    }
}
