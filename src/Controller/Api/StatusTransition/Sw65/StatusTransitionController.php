<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Api\StatusTransition\Sw65;

use PaynlPayment\Shopware6\Controller\Api\StatusTransition\StatusTransitionControllerBase;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"api"}, "auth_required"=true, "auth_enabled"=true})
 */
class StatusTransitionController extends StatusTransitionControllerBase
{
}
