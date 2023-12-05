<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\CheckoutHistoryBack;

use PaynlPayment\Shopware6\Subscriber\OrderSubscriber;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

class CheckoutHistoryBackControllerBase extends StorefrontController
{
    /** @var RequestStack */
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @Route(
     *     "/checkout/history/back",
     *     name="frontend.checkout.Paynl.history.back",
     *     options={"seo"="false"},
     *     methods={"GET"},
     *     defaults={"_loginRequired"=true, "_loginRequiredAllowGuest"=true, "_noStore"=true, "_routeScope"={"storefront"}}
     * )
     */
    public function historyBackProxy(): Response
    {
        if ($lastOrderId = $this->getBackOrderHistoryData()) {
            return $this->redirectToRoute('frontend.account.edit-order.page', ['orderId' => $lastOrderId]);
        }
        return $this->redirectToRoute('frontend.account.order.page');
    }

    private function getBackOrderHistoryData(): ?string
    {
        if (!$this->requestStack->getSession()) {
            return null;
        }
        return $this->requestStack->getSession()->get(OrderSubscriber::LAST_PLACED_ORDER_ID);
    }
}
