<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\Notification\Sw65;

use PaynlPayment\Shopware6\Controller\Storefront\Notification\NotificationControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront'], 'auth_required' => true, 'auth_enabled' => true])]
class NotificationController extends NotificationControllerBase
{
    #[Route(
        path: '/PaynlPayment/notify',
        name: 'frontend.PaynlPayment.notify',
        options: ['seo' => false],
        defaults: ['csrf_protected' => false, '_routeScope' => ['storefront']],
        methods: ['GET', 'POST']
    )]
    public function notify(Request $request): Response
    {
        return $this->getNotifyResponse($request);
    }
}
