<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\PayPal;

use PaynlPayment\Shopware6\Components\ExpressCheckoutUtil;
use PaynlPayment\Shopware6\Components\PayPalExpress\PayPalExpress;
use PaynlPayment\Shopware6\Enums\ExpressCheckoutEnum;
use PaynlPayment\Shopware6\Service\Cart\CartBackupService;
use PaynlPayment\Shopware6\Service\CartService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartDeleteRoute;
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
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class PayPalExpressController extends StorefrontController
{
    private const SNIPPET_ERROR = 'payment.paypalExpressCheckout.paymentError';

    private ExpressCheckoutUtil $expressCheckoutUtil;
    private PayPalExpress $paypalExpress;
    private CartService $cartService;
    private CartBackupService $cartBackupService;
    private RouterInterface $router;
    private AbstractContextSwitchRoute $contextSwitchRoute;
    private AbstractCartDeleteRoute $cartDeleteRoute;
    private LoggerInterface $logger;
    private ?FlashBag $flashBag;

    public function __construct(
        ExpressCheckoutUtil $expressCheckoutUtil,
        PayPalExpress $paypalExpress,
        CartService $cartService,
        CartBackupService $cartBackupService,
        RouterInterface $router,
        AbstractContextSwitchRoute $contextSwitchRoute,
        AbstractCartDeleteRoute $cartDeleteRoute,
        LoggerInterface $logger,
        ?FlashBag $flashBag
    ) {
        $this->expressCheckoutUtil = $expressCheckoutUtil;
        $this->paypalExpress = $paypalExpress;
        $this->cartService = $cartService;
        $this->cartBackupService = $cartBackupService;
        $this->router = $router;
        $this->contextSwitchRoute = $contextSwitchRoute;
        $this->cartDeleteRoute = $cartDeleteRoute;
        $this->logger = $logger;
        $this->flashBag = $flashBag;
    }

    #[Route('/PaynlPayment/paypal-express/prepare-cart', name: 'frontend.account.PaynlPayment.paypal-express.prepare-cart', options: ['seo' => false], defaults: ['XmlHttpRequest' => true, 'csrf_protected' => false], methods: ['POST'])]
    public function expressPrepareCart(Request $request, SalesChannelContext $context): Response
    {
        $this->contextSwitchRoute->switchContext(new RequestDataBag([
            SalesChannelContextService::PAYMENT_METHOD_ID => $request->get('paymentMethodId'),
        ]), $context);

        if ($request->request->getBoolean('deleteCart')) {
            $this->cartDeleteRoute->delete($context);
        }

        return new NoContentResponse();
    }

    #[Route('/PaynlPayment/paypal-express/start-payment', name: 'frontend.account.PaynlPayment.paypal-express.start-payment', options: ['seo' => false], defaults: ['XmlHttpRequest' => true, 'csrf_protected' => false], methods: ['POST'])]
    public function startPayment(SalesChannelContext $context, Request $request): Response
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

    #[Route(path: '/PaynlPayment/paypal-express/create-payment', name: 'frontend.account.PaynlPayment.paypal-express.create-payment', options: ['seo' => false], defaults: ['XmlHttpRequest' => true, 'csrf_protected' => false], methods: ['POST'])]
    public function createPayment(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        try {
            $orderId = $request->request->get('token');

            $payOrder = $this->paypalExpress->createPayPaymentTransaction($orderId, $salesChannelContext);

            $responseArray = [
                'redirectUrl' => $payOrder->getPaymentUrl()
            ];

            return new Response(json_encode($responseArray));
        } catch (Throwable $exception) {
            return new Response(json_encode(['error' => $exception->getMessage()]), 400);
        }
    }

    #[Route(path: '/PaynlPayment/paypal-express/add-error', name: 'frontend.account.PaynlPayment.paypal-express.add-error', options: ['seo' => false, 'csrf_protected' => false], methods: ['POST'])]
    public function addErrorMessage(Request $request): Response
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

    #[Route(path: '/PaynlPayment/paypal-express/finish-page', name: 'frontend.account.PaynlPayment.paypal-express.finish-page', options: ['seo' => false], methods: ['POST', 'GET'])]
    public function finishPage(Request $request, SalesChannelContext $context): Response
    {
        $orderId = $request->get('orderId');

        if (!$this->expressCheckoutUtil->isNotCompletedOrder($orderId, $context->getContext())) {
            return $this->redirectToRoute('frontend.checkout.finish.page', ['orderId' => $orderId]);
        }

        if ($context->getCustomer() === null) {
            return $this->redirectToRoute('frontend.home.page');
        }

        try {
            $this->expressCheckoutUtil->logoutCustomer($context);

            $parameters = [];
        } catch (ConstraintViolationException $formViolations) {
            $parameters = ['formViolations' => $formViolations];
        }

        return $this->redirectToRoute('frontend.home.page', $parameters);
    }

    protected function getCheckoutConfirmPage(RouterInterface $router): string
    {
        return $router->generate(
            'frontend.checkout.confirm.page',
            [],
            $router::ABSOLUTE_URL
        );
    }

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
