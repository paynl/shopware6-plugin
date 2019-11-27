<?php declare(strict_types=1);

namespace PaynlPayment\Storefront\Controller;

use PaynlPayment\Components\Api;
use PaynlPayment\Components\Config;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * @RouteScope(scopes={"storefront"})
 */
class RefundController extends StorefrontController
{
    private $paynlApi;
    private $paynlConfig;
    private $transactionRepository;
    private $productRepository;

    public function __construct(
        Api $paynlApi,
        Config $paynlConfig,
        EntityRepositoryInterface $transactionRepository,
        EntityRepositoryInterface $productRepository
    ) {
        $this->paynlApi = $paynlApi;
        $this->paynlConfig = $paynlConfig;
        $this->transactionRepository = $transactionRepository;
        $this->productRepository = $productRepository;
    }

    /**
     * @Route(
     *     "/paynl-payment/get-refund-data",
     *     name="frontend.PaynlPayment.getRefundData",
     *     options={"seo"="false"},
     *     methods={"GET"}
     *     )
     */
    public function getRefundData(Request $request): JsonResponse
    {
        $paynlTransactionId = $request->get('transactionId');
        $apiTransaction = $this->paynlApi->getTransaction($paynlTransactionId);
        $refundedAmount = $apiTransaction->getRefundedAmount();
        $availableForRefund = $apiTransaction->getAmount() - $refundedAmount;

        return new JsonResponse([
            'refundedAmount' => $refundedAmount,
            'availableForRefund' => $availableForRefund
        ]);
    }

    /**
     * @Route("/paynl-payment/refund", name="frontend.PaynlPayment.refund", options={"seo"="false"}, methods={"POST"})
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
            $refundResult = $this->paynlApi->refund($paynlPaymentId, $amount, $description);
            $this->restock($products);
            $messages[] = [
                'type' => 'success',
                'content' => 'Refund successful (' . $refundResult->getData()['description'] . ')'
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
        $criteria = new Criteria();
        $context = Context::createDefaultContext();

        foreach ($products as $product) {
            if (isset($product['rstk']) && $product['rstk'] == true) {
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

        $this->productRepository->update($data, $context);
    }
}
