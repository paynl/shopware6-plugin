<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Api\Refund;

use Paynl\Error;
use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Entity\PaynlTransactionEntity;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class RefundControllerBase extends AbstractController
{
    private $paynlApi;
    private $paynlConfig;
    /** @var LoggerInterface */
    private $logger;
    private $transactionRepository;
    private $productRepository;
    private $paynlTransactionRepository;

    /** @var ProcessingHelper */
    private $processingHelper;

    public function __construct(
        Api $paynlApi,
        Config $paynlConfig,
        LoggerInterface $logger,
        EntityRepository $transactionRepository,
        EntityRepository $productRepository,
        ProcessingHelper $processingHelper,
        EntityRepository $paynlTransactionRepository
    ) {
        $this->paynlApi = $paynlApi;
        $this->paynlConfig = $paynlConfig;
        $this->logger = $logger;
        $this->transactionRepository = $transactionRepository;
        $this->productRepository = $productRepository;
        $this->processingHelper = $processingHelper;
        $this->paynlTransactionRepository = $paynlTransactionRepository;
    }

    protected function getRefundDataResponse(Request $request): JsonResponse
    {
        $paynlTransactionId = $request->query->get('transactionId');
        $paynlTransaction = $this->getPaynlTransactionEntityByPaynlTransactionId($paynlTransactionId);
        $salesChannelId = $paynlTransaction->getOrder()->getSalesChannelId();

        try {
            $this->logger->info('Refund data for transaction ' . $paynlTransactionId);

            $apiTransaction = $this->paynlApi->getTransaction($paynlTransactionId, $salesChannelId);
            $refundedAmount = $apiTransaction->getRefundedAmount();
            $availableForRefund = $apiTransaction->getAmount() - $refundedAmount;

            return new JsonResponse([
                'refundedAmount' => $refundedAmount,
                'availableForRefund' => $availableForRefund
            ]);
        } catch (Error\Api $exception) {
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

        $paynlTransaction = $this->getPaynlTransactionEntityByPaynlTransactionId($paynlTransactionId);
        $salesChannelId = $paynlTransaction->getOrder()->getSalesChannelId();
        $salesChannel = $paynlTransaction->getOrder()->getSalesChannel();

        try {
            $this->logger->info('Start refunding for transaction ' . $paynlTransactionId, [
                'transactionId' => $paynlTransactionId,
                'amount' => $amount,
                'salesChannel' => $salesChannel ? $salesChannel->getName() : ''
            ]);

            $this->paynlApi->refund($paynlTransactionId, $amount, $salesChannelId, $description);
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
