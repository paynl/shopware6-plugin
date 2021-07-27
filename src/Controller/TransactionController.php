<?php

namespace PaynlPayment\Shopware6\Controller;

use PaynlPayment\Shopware6\Components\Api;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Helper\ProcessingHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class TransactionController extends AbstractController
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
     * @Route("/api/paynl/do-instore-payment", name="api.PaynlPayment.doInstorePaymentSW64", methods={"POST"})
     */
    public function doInstorePaymentSW64(Request $request): JsonResponse
    {
        return $this->getRefundDataResponse($request);
    }

}
