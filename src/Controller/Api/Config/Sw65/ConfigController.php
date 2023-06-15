<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Api\Config\Sw65;

use PaynlPayment\Shopware6\Controller\Api\Config\ConfigControllerBase;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"api"}, "auth_required"=true, "auth_enabled"=true})
 */
class ConfigController extends ConfigControllerBase
{
}
