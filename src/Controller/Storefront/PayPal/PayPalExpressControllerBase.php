<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\PayPal;

use PaynlPayment\Shopware6\Components\ExpressCheckoutUtil;
use PaynlPayment\Shopware6\Components\PayPalExpress\PayPalExpress;
use PaynlPayment\Shopware6\Enums\ExpressCheckoutEnum;
use PaynlPayment\Shopware6\Service\Cart\CartBackupService;
use PaynlPayment\Shopware6\Service\CartService;
use PaynlPayment\Shopware6\Service\OrderService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartDeleteRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLogoutRoute;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

class PayPalExpressControllerBase extends StorefrontController
{
    private const SNIPPET_ERROR = 'payment.paypalExpressCheckout.paymentError';

    /** @var ExpressCheckoutUtil */
    private $expressCheckoutUtil;

    /** @var PayPalExpress */
    private $paypalExpress;

    /** @var CartService */
    private $cartService;

    /** @var CartBackupService */
    private $cartBackupService;

    /** @var OrderService */
    private $orderService;

    /** @var RouterInterface */
    private $router;

    /** @var AbstractLogoutRoute */
    private $logoutRoute;

    /** @var AbstractContextSwitchRoute */
    private $contextSwitchRoute;

    /** @var AbstractCartDeleteRoute */
    private $cartDeleteRoute;

    /** @var LoggerInterface */
    private $logger;

    /** @var ?FlashBag */
    private $flashBag;

    public function __construct(
        ExpressCheckoutUtil $expressCheckoutUtil,
        PayPalExpress $paypalExpress,
        CartService $cartService,
        CartBackupService $cartBackupService,
        OrderService $orderService,
        RouterInterface $router,
        AbstractLogoutRoute $logoutRoute,
        AbstractContextSwitchRoute $contextSwitchRoute,
        AbstractCartDeleteRoute $cartDeleteRoute,
        LoggerInterface $logger,
        ?FlashBag $flashBag
    ) {
        $this->expressCheckoutUtil = $expressCheckoutUtil;
        $this->paypalExpress = $paypalExpress;
        $this->cartService = $cartService;
        $this->cartBackupService = $cartBackupService;
        $this->orderService = $orderService;
        $this->router = $router;
        $this->logoutRoute = $logoutRoute;
        $this->contextSwitchRoute = $contextSwitchRoute;
        $this->cartDeleteRoute = $cartDeleteRoute;
        $this->logger = $logger;
        $this->flashBag = $flashBag;
    }

    public function getExpressPrepareCartResponse(Request $request, SalesChannelContext $context): Response
    {
        $this->contextSwitchRoute->switchContext(new RequestDataBag([
            SalesChannelContextService::PAYMENT_METHOD_ID => $request->get('paymentMethodId'),
        ]), $context);

        if ($request->request->getBoolean('deleteCart')) {
            $this->cartDeleteRoute->delete($context);
        }

        return new NoContentResponse();
    }

    public function getStartPaymentResponse(SalesChannelContext $context, Request $request): Response
    {
        try {
            $this->cartBackupService->clearBackup($context);

            $paypalID = $this->expressCheckoutUtil->getActivePayPalID($context);

            $context = $this->cartService->updatePaymentMethod($context, $paypalID);

            $email = ExpressCheckoutEnum::CUSTOMER_EMAIL;
            $firstname = ExpressCheckoutEnum::CUSTOMER_FIRST_NAME;
            $lastname = ExpressCheckoutEnum::CUSTOMER_LAST_NAME;
            $street = ExpressCheckoutEnum::CUSTOMER_ADDRESS_STREET;
            $city = ExpressCheckoutEnum::CUSTOMER_ADDRESS_CITY;
            $zipcode = ExpressCheckoutEnum::CUSTOMER_ADDRESS_ZIP;

            $newContext = $this->expressCheckoutUtil->prepareCustomer(
                $firstname,
                $lastname,
                $email,
                $street,
                $zipcode,
                $city,
                $paypalID,
                $context
            );

            $order = $this->expressCheckoutUtil->createOrder($newContext);
            $countryCode = $order->getBillingAddress()->getCountry()
                ? $order->getBillingAddress()->getCountry()->getIso()
                : null;

            $returnUrl = $this->getCheckoutFinishPage($order->getId(), $this->router);

            $paymentId = $this->paypalExpress->createPayment(
                $order,
                $returnUrl,
                $firstname,
                $lastname,
                $street,
                $zipcode,
                $city,
                $countryCode,
                $newContext
            );

            return new Response(json_encode(['token' => $paymentId]));
        } catch (\Throwable $ex) {
            $returnUrl = $this->getCheckoutConfirmPage($this->router);

            if ($this->flashBag !== null) {
                $this->flashBag->add('danger', $this->trans(self::SNIPPET_ERROR));
            }

            return new RedirectResponse($returnUrl);
        }
    }

    public function getCreatePaymentResponse(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        try {
            $orderId = $request->request->get('token');

            $payOrder = $this->paypalExpress->createPayPaymentTransaction($orderId, $salesChannelContext);

            $responseArray = [
                'redirectUrl' => $payOrder->getLinks()->getRedirect()
            ];

            return new Response(json_encode($responseArray));
        } catch (Throwable $exception) {
            return new Response(json_encode(['error' => $exception->getMessage()]), 400);
        }
    }

    public function getFinishPageResponse(Request $request, SalesChannelContext $context): Response
    {
        $orderId = $request->get('orderId');

        if (!$this->expressCheckoutUtil->isNotCompletedOrder($orderId, $context->getContext())) {
            return $this->redirectToRoute('frontend.checkout.finish.page', ['orderId' => $orderId]);
        }

        if ($context->getCustomer() === null) {
            return $this->redirectToRoute('frontend.home.page');
        }

        try {
            $this->logoutRoute->logout($context, new RequestDataBag());

            $parameters = [];
        } catch (ConstraintViolationException $formViolations) {
            $parameters = ['formViolations' => $formViolations];
        }

        return $this->redirectToRoute('frontend.home.page', $parameters);
    }

    public function getAddErrorMessageResponse(Request $request): Response
    {
        if ($request->request->getBoolean('cancel')) {
            $this->addFlash(static::DANGER, $this->trans('checkout.messages.cancelledTransaction'));
            $this->logger->notice('Storefront checkout cancellation');
        } else {
            $this->addFlash(static::DANGER, $this->trans('checkout.messages.cancelledTransaction'));
            $this->logger->notice('Storefront checkout error', ['error' => $request->request->get('error')]);
        }

        return new NoContentResponse();
    }

    /**
     * @param RouterInterface $router
     * @return string
     */
    protected function getCheckoutConfirmPage(RouterInterface $router): string
    {
        return $router->generate(
            'frontend.checkout.confirm.page',
            [],
            $router::ABSOLUTE_URL
        );
    }

    /**
     * @param string $orderId
     * @param RouterInterface $router
     * @return string
     */
    protected function getCheckoutFinishPage(string $orderId, RouterInterface $router): string
    {
        return $router->generate(
            'frontend.account.PaynlPayment.paypal-express.finish-page',
            [
                'orderId' => $orderId,
            ],
            $this->router::ABSOLUTE_URL
        );
    }
}
