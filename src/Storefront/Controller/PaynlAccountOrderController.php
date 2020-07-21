<?php

namespace PaynlPayment\Shopware6\Storefront\Controller;

use Shopware\Core\Checkout\Order\SalesChannel\AbstractCancelOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractSetPaymentOrderRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractHandlePaymentMethodRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\AccountOrderController;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoader;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;

/**
* @RouteScope(scopes={"storefront"})
 */
class PaynlAccountOrderController extends AccountOrderController
{
    /**
     * @var Session
     */
    private $session;

    public function __construct(
        AccountOrderPageLoader $orderPageLoader,
        AbstractOrderRoute $orderRoute,
        RequestCriteriaBuilder $requestCriteriaBuilder,
        AccountEditOrderPageLoader $accountEditOrderPageLoader,
        ContextSwitchRoute $contextSwitchRoute,
        AbstractCancelOrderRoute $orderStateChangeRoute,
        AbstractSetPaymentOrderRoute $setPaymentOrderRoute,
        AbstractHandlePaymentMethodRoute $handlePaymentMethodRoute,
        Session $session
    ) {
        parent::__construct(
            $orderPageLoader,
            $orderRoute,
            $requestCriteriaBuilder,
            $accountEditOrderPageLoader,
            $contextSwitchRoute,
            $orderStateChangeRoute,
            $setPaymentOrderRoute,
            $handlePaymentMethodRoute,
            $session
        );
        $this->session = $session;
    }

    public function orderOverview(Request $request, SalesChannelContext $context): Response
    {
        return parent::orderOverview($request, $context);
    }

    public function orderSingleOverview(Request $request, SalesChannelContext $context): Response
    {
        return parent::orderSingleOverview($request, $context);
    }

    public function ajaxOrderDetail(Request $request, SalesChannelContext $context): Response
    {
        return parent::ajaxOrderDetail($request, $context);
    }

    public function cancelOrder(Request $request, SalesChannelContext $context): Response
    {
        return parent::cancelOrder($request, $context);
    }

    public function editOrder(string $orderId, Request $request, SalesChannelContext $context): Response
    {
        return parent::editOrder($orderId, $request, $context);
    }

    public function orderChangePayment(string $orderId, Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $this->session->set('paynlIssuer', $request->get('paynlIssuer'));

        return parent::orderChangePayment($orderId, $request, $salesChannelContext);
    }

    public function updateOrder(string $orderId, Request $request, SalesChannelContext $salesChannelContext): Response
    {
        return parent::updateOrder($orderId, $request, $salesChannelContext);
    }
}
