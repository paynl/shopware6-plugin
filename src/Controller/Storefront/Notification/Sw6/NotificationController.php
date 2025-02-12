<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\Notification\Sw6;

use PaynlPayment\Shopware6\Controller\Storefront\Notification\NotificationControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;

/**
 * @RouteScope(scopes={"storefront"})
 */
class NotificationController extends NotificationControllerBase
{
    /**
     * @Route(
     *     "/PaynlPayment/notify",
     *     name="frontend.PaynlPayment.notify",
     *     options={"seo"="false"},
     *     methods={"POST", "GET"},
     *     defaults={"csrf_protected"=false, "_routeScope"={"storefront"}}
     * )
     */
    public function notify(Request $request): Response
    {
        return $this->getNotifyResponse($request);
    }
}
