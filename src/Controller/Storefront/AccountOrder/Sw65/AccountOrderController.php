<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\AccountOrder\Sw65;

use PaynlPayment\Shopware6\Controller\Storefront\AccountOrder\AccountOrderControllerBase;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront'], 'auth_required' => true, 'auth_enabled' => true])]
class AccountOrderController extends AccountOrderControllerBase
{
}
