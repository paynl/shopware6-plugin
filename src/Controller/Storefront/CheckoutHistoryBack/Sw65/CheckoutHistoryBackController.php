<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\CheckoutHistoryBack\Sw65;

use PaynlPayment\Shopware6\Controller\Storefront\CheckoutHistoryBack\CheckoutHistoryBackControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class CheckoutHistoryBackController extends CheckoutHistoryBackControllerBase
{

    #[Route('/checkout/history/back', name: 'frontend.checkout.Paynl.history.back', options: ['seo' => false], defaults: ['_loginRequired' => true, '_loginRequiredAllowGuest' => true, '_noStore' => true, '_routeScope' => ['storefront']], methods: ['GET'])]
    public function historyBackProxy(): Response
    {
        return $this->getHistoryBackProxy();
    }
}
