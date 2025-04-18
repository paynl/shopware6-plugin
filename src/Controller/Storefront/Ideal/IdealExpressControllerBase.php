<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\Ideal;

use PaynlPayment\Shopware6\Components\ExpressCheckoutUtil;
use PaynlPayment\Shopware6\Components\IdealExpress\IdealExpress;
use PaynlPayment\Shopware6\Enums\ExpressCheckoutEnum;
use PaynlPayment\Shopware6\Service\Cart\CartBackupService;
use PaynlPayment\Shopware6\Service\CartService;
use PaynlPayment\Shopware6\Service\OrderService;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

class IdealExpressControllerBase extends StorefrontController
{
    private const SNIPPET_ERROR = 'payment.idealExpressCheckout.paymentError';

    /** @var ExpressCheckoutUtil */
    private $expressCheckoutUtil;

    /** @var IdealExpress */
    private $idealExpress;

    /** @var CartService */
    private $cartService;

    /** @var CartBackupService */
    private $cartBackupService;

    /** @var OrderService */
    private $orderService;

    /** @var RouterInterface */
    private $router;

    /** @var ?FlashBag */
    private $flashBag;

    public function __construct(
        ExpressCheckoutUtil $expressCheckoutUtil,
        IdealExpress $idealExpress,
        CartService $cartService,
        CartBackupService $cartBackupService,
        OrderService $orderService,
        RouterInterface $router,
        ?FlashBag $flashBag
    ) {
        $this->expressCheckoutUtil = $expressCheckoutUtil;
        $this->idealExpress = $idealExpress;
        $this->cartService = $cartService;
        $this->cartBackupService = $cartBackupService;
        $this->orderService = $orderService;
        $this->router = $router;
        $this->flashBag = $flashBag;
    }

    public function getStartPaymentResponse(SalesChannelContext $context, Request $request): Response
    {
        try {
            $this->cartBackupService->clearBackup($context);

            $idealID = $this->expressCheckoutUtil->getActiveIdealID($context);

            $context = $this->cartService->updatePaymentMethod($context, $idealID);

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
                $idealID,
                $context
            );

            $order = $this->expressCheckoutUtil->createOrder($newContext);
            $countryCode = $order->getBillingAddress()->getCountry()
                ? $order->getBillingAddress()->getCountry()->getIso()
                : null;

            $returnUrl = $this->getCheckoutFinishPage($order->getId(), $this->router);

            $redirectUrl = $this->idealExpress->createPayment(
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

            return new RedirectResponse($redirectUrl);
        } catch (Throwable $ex) {
            $returnUrl = $this->getCheckoutConfirmPage($this->router);

            if ($this->flashBag !== null) {
                $this->flashBag->add('danger', $this->trans(self::SNIPPET_ERROR));
            }

            return new RedirectResponse($returnUrl);
        }
    }

    public function getFinishPaymentResponse(RequestDataBag $data, SalesChannelContext $context): Response
    {
        $orderNumber = (string) $data->get('object')->get('reference');
        $dataObject = (array) $data->all()['object'] ?? [];

        try {
            $order = $this->orderService->getOrderByNumber($orderNumber, $context->getContext());

            $this->idealExpress->updateOrder($order,$dataObject, $context);

            $this->idealExpress->updateOrderCustomer($order->getOrderCustomer(), $dataObject, $context);

            $this->idealExpress->updateCustomer($order->getOrderCustomer()->getCustomer(), $dataObject, $context);

            $responseText = $this->idealExpress->processNotify($dataObject);

            return new Response($responseText);
        } catch (Throwable $ex) {
            $responseText = sprintf(
                'FALSE| Error "%s" in file %s',
                $ex->getMessage(),
                $ex->getFile()
            );

            return new Response($responseText);
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
            $this->expressCheckoutUtil->logoutCustomer($context);

            $parameters = [];
        } catch (ConstraintViolationException $formViolations) {
            $parameters = ['formViolations' => $formViolations];
        }

        return $this->redirectToRoute('frontend.home.page', $parameters);
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
            'frontend.account.PaynlPayment.ideal-express.finish-page',
            [
                'orderId' => $orderId,
            ],
            $this->router::ABSOLUTE_URL
        );
    }
}
