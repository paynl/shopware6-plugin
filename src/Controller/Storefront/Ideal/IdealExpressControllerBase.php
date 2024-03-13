<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\Ideal;

use Exception;
use PaynlPayment\Shopware6\Components\IdealExpress\IdealExpress;
use PaynlPayment\Shopware6\Repository\Country\CountryRepositoryInterface;
use PaynlPayment\Shopware6\Repository\Salutation\SalutationRepositoryInterface;
use PaynlPayment\Shopware6\Service\Cart\CartBackupService;
use PaynlPayment\Shopware6\Service\CartService;
use PaynlPayment\Shopware6\Service\OrderService;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractRegisterRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannel\SalesChannelContextSwitcher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

class IdealExpressControllerBase extends StorefrontController
{
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
        IdealExpress $idealExpress,
        CartService $cartService,
        CartBackupService $cartBackupService,
        OrderService $orderService,
        RouterInterface $router,
        ?FlashBag $flashBag
    ) {
        $this->idealExpress = $idealExpress;
        $this->cartService = $cartService;
        $this->cartBackupService = $cartBackupService;
        $this->orderService = $orderService;
        $this->router = $router;
        $this->flashBag = $flashBag;
    }

    #[Route(
        path: '/PaynlPayment/paypal/start-payment',
        name: 'frontend.account.PaynlPayment.paypal.start-payment',
        options: ['seo' => false],
        methods: ['GET'])
    ]
    public function startPayment(SalesChannelContext $context, Request $request): Response
    {
        $finishUrl = (string)$request->get('finishUrl', '');

        try {

            $this->cartBackupService->clearBackup($context);

            $idealID = $this->idealExpress->getActiveIdealID($context);

            $this->cartService->updatePaymentMethod($context, $idealID);

            $email = 'temp@temp.com';
            $firstname = 'Temp';
            $lastname = 'Temp';
            $street = 'temp';
            $city = 'Temp';
            $zipcode = '23456';
            $countryCode = 'de';

            $newContext = $this->idealExpress->prepareCustomer(
                $firstname,
                $lastname,
                $email,
                $street,
                $zipcode,
                $city,
                $countryCode,
                $context
            );

            $order = $this->idealExpress->createOrder($newContext);

            $this->idealExpress->createPayment(
                $order,
                $finishUrl,
                $firstname,
                $lastname,
                $street,
                $zipcode,
                $city,
                $countryCode,
                $context
            );

            $returnUrl = $this->getCheckoutFinishPage($order->getId(), $this->router);

            return new RedirectResponse($returnUrl);
        } catch (\Throwable $ex) {
            $returnUrl = $this->getCheckoutConfirmPage($this->router);

            if ($this->flashBag !== null) {
                $this->flashBag->add('danger', $this->trans('paynl.error'));
            }

            return new RedirectResponse($returnUrl);
        }
    }


    #[Route(
        path: '/PaynlPayment/paypal/finish-payment',
        name: 'frontend.account.PaynlPayment.paypal.finish-payment',
        options: ['seo' => false],
        methods: ['POST', 'GET'])
    ]
    public function finishPayment(RequestDataBag $data, SalesChannelContext $context): Response
    {
        $orderNumber = (string)$data->get('reference', '');

        try {
            $order = $this->orderService->getOrderByNumber($orderNumber, $context->getContext());

            $this->idealExpress->updateOrder($order, $data->all(), $context);

            $this->idealExpress->updateOrderCustomer($order->getOrderCustomer(), $data->all(), $context);

            $this->idealExpress->updateCustomer($order->getOrderCustomer()->getCustomer(), $data->all(), $context);

            return new Response(json_encode(['success' => true]));
        } catch (Throwable $ex) {
            return new Response($ex->getMessage());
        }
    }

    /**
     * @param RouterInterface $router
     * @return string
     */
    public function getCheckoutConfirmPage(RouterInterface $router): string
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
    public function getCheckoutFinishPage(string $orderId, RouterInterface $router): string
    {
        return $router->generate(
            'frontend.checkout.finish.page',
            [
                'orderId' => $orderId,
            ],
            $router::ABSOLUTE_URL
        );
    }
}
