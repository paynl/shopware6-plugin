<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\Notification;

use PaynlPayment\Shopware6\Service\Notification\NotificationFacade;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class NotificationController extends StorefrontController
{
    private NotificationFacade $notificationFacade;

    public function __construct(NotificationFacade $notificationFacade)
    {
        $this->notificationFacade = $notificationFacade;
    }

    #[Route('/PaynlPayment/notify', name: 'frontend.PaynlPayment.notify', options: ['seo' => false], methods: ['GET', 'POST'])]
    public function notify(Request $request): Response
    {
        $body = $this->notificationFacade->onNotify($request);

        return new Response($body);
    }
}
