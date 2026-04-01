<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Api\Notification;

use PaynlPayment\Shopware6\Service\Notification\NotificationFacade;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '/api/paynl', defaults: ['_routeScope' => ['api'], 'auth_required' => false, 'auth_enabled' => false])]
class NotificationController extends AbstractController
{
    private NotificationFacade $notificationFacade;

    public function __construct(NotificationFacade $notificationFacade)
    {
        $this->notificationFacade = $notificationFacade;
    }

    #[Route('/notify', name: 'api.PaynlPayment.notify', methods: ['GET', 'POST'])]
    public function notify(Request $request): Response
    {
        $body = $this->notificationFacade->onNotify($request);

        return new Response($body);
    }
}
