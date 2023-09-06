<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Api\Config\Sw6;

use PaynlPayment\Shopware6\Controller\Api\Config\ConfigControllerBase;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"api"})
 * @Route(defaults={"auth_required"=true, "auth_enabled"=true})
 */
class ConfigController extends ConfigControllerBase
{
}
