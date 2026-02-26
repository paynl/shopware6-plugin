<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Api\Notification;

use PaynlPayment\Shopware6\Service\Notification\NotificationFacade;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends AbstractController
{
    private NotificationFacade $notificationFacade;

    public function __construct(NotificationFacade $notificationFacade)
    {
        $this->notificationFacade = $notificationFacade;
    }

    public function notify(Request $request): Response
    {
        $body = $this->notificationFacade->onNotify($request);

        return new Response($body);
    }
}
