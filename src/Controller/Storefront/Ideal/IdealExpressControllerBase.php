<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\Ideal;

use Exception;
use PaynlPayment\Shopware6\Components\IdealExpress\IdealExpress;
use PaynlPayment\Shopware6\Repository\Country\CountryRepositoryInterface;
use PaynlPayment\Shopware6\Repository\Salutation\SalutationRepositoryInterface;
use PaynlPayment\Shopware6\Service\Cart\CartBackupService;
use PaynlPayment\Shopware6\Service\CartService;
use PayonePayment\Storefront\Struct\CheckoutCartPaymentData;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractRegisterRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannel\SalesChannelContextSwitcher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Shopware\Core\System\Salutation\SalutationEntity;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

class IdealExpressControllerBase extends StorefrontController
{
    private const SNIPPET_ERROR = 'molliePayments.payments.applePayDirect.paymentError';

    /** @var IdealExpress */
    private $idealExpress;

    /** @var CartService */
    private $cartService;
    /** @var CartBackupService */
    private $cartBackupService;
    /** @var AbstractRegisterRoute */
    private $registerRoute;
    /** @var AccountService */
    private $accountService;
    /** @var AbstractSalesChannelContextFactory */
    private $salesChannelContextFactory;
    /** @var EntityRepository */
    private $salutationRepository;
    /** @var EntityRepository */
    private $countryRepository;
    /** @var SalesChannelContextSwitcher */
    private $salesChannelContextSwitcher;
    /** @var RouterInterface */
    private $router;
    /** @var ?FlashBag */
    private $flashBag;

    public function __construct(
        IdealExpress                       $idealExpress,
        CartService                        $cartService,
        CartBackupService                  $cartBackupService,
        AbstractRegisterRoute              $registerRoute,
        AccountService                     $accountService,
        AbstractSalesChannelContextFactory $salesChannelContextFactory,
        SalutationRepositoryInterface      $salutationRepository,
        CountryRepositoryInterface         $countryRepository,
        SalesChannelContextSwitcher        $salesChannelContextSwitcher,
        RouterInterface                    $router,
        ?FlashBag                          $flashBag
    ) {
        $this->idealExpress = $idealExpress;
        $this->cartService = $cartService;
        $this->cartBackupService = $cartBackupService;
        $this->registerRoute = $registerRoute;
        $this->accountService = $accountService;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->salutationRepository = $salutationRepository;
        $this->countryRepository = $countryRepository;
        $this->salesChannelContextSwitcher = $salesChannelContextSwitcher;
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
        try {
            # we clear our cart backup now
            # we are in the user redirection process where a restoring wouldnt make sense
            # because from now on we would end on the cart page where we could even switch payment method.
            $this->cartBackupService->clearBackup($context);

            $paypalID = '013d407166ec4fa56eb1e1f8cbe183b9';

            // Start payment on IDEAL PAY. API

            $this->cartService->updatePaymentMethod($context, $paypalID);

            return new RedirectResponse($this->router->generate('frontend.account.PaynlPayment.paypal.finish-payment'));
        } catch (\Throwable $ex) {
            # if we have an error here, we have to redirect to the confirm page
            $returnUrl = $this->getCheckoutConfirmPage($this->router);
            # also add an error for our target page
            if ($this->flashBag !== null) {
                $this->flashBag->add('danger', $this->trans(self::SNIPPET_ERROR));
            }

            return new RedirectResponse($returnUrl);
        }
    }


    #[Route(
        path: '/PaynlPayment/paypal/finish-payment',
        name: 'frontend.account.PaynlPayment.paypal.finish-payment',
        options: ['seo' => false],
        methods: ['GET'])
    ]
    public function finishPayment(RequestDataBag $data, SalesChannelContext $context): Response
    {
        $email = (string)$data->get('email', '');
        $firstname = (string)$data->get('firstname', '');
        $lastname = (string)$data->get('lastname', '');
        $street = (string)$data->get('street', '');
        $city = (string)$data->get('city', '');
        $zipcode = (string)$data->get('postalCode', '');
        $countryCode = (string)$data->get('countryCode', '');

        $finishUrl = (string)$data->get('finishUrl', '');
        $errorUrl = (string)$data->get('errorUrl', '');

        $email = 'test@test.com';
        $firstname = 'Test';
        $lastname = 'Test';
        $street = 'test';
        $city = 'test';
        $zipcode = '214';
        $countryCode = 'de';

        # make sure to create a customer if necessary
        # then update to our IDEAL Express payment method
        # and return the new context
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

        # we only start our TRY/CATCH here!
        # we always need to throw exceptions on an API level
        # but if something BELOW breaks, we want to navigate to the error page.
        # customers are ready, data is ready, but the handling has a problem.

        try {
            # create our new Shopware Order
            $order = $this->idealExpress->createOrder($newContext);

            # now create the Mollie payment for it
            # there should not be a checkout URL required for IDEAL Express,
            # so we just create the payment and redirect.
            $this->idealExpress->createPayment(
                $order,
                $finishUrl,
                $firstname,
                $lastname,
                $street,
                $zipcode,
                $city,
                $countryCode,
                $newContext
            );

            $returnUrl = $this->getCheckoutFinishPage($order->getId(), $this->router);

            return new RedirectResponse($returnUrl);
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

    protected function addCartExtension(
        Cart $cart,
        SalesChannelContext $context,
        string $workOrderId
    ): void {
        $cartData = new CheckoutCartPaymentData();

        $cartData->assign(array_filter([
            'workOrderId' => $workOrderId,
            'cartHash' => $this->cartHasher->generate($cart, $context),
        ]));

        $cart->addExtension(CheckoutCartPaymentData::EXTENSION_NAME, $cartData);

        $this->cartService->recalculate($cart, $context);
    }

    private function getCustomerDataBagFromResponse(array $response, Context $context): RequestDataBag
    {
        $salutationId = $this->getSalutationId($context);
        $countryId = $this->getCountryIdByCode($response['addpaydata']['shipping_country'], $context);

        return new RequestDataBag([
            'guest' => true,
            'salutationId' => $salutationId,
            'email' => $response['addpaydata']['email'],
            'firstName' => $response['addpaydata']['shipping_firstname'],
            'lastName' => $response['addpaydata']['shipping_lastname'],
            'acceptedDataProtection' => true,
            'billingAddress' => array_filter([
                'firstName' => $response['addpaydata']['shipping_firstname'],
                'lastName' => $response['addpaydata']['shipping_lastname'],
                'salutationId' => $salutationId,
                'street' => $response['addpaydata']['shipping_street'],
                'zipcode' => $response['addpaydata']['shipping_zip'],
                'countryId' => $countryId,
                'phone' => $response['addpaydata']['telephonenumber'],
                'city' => $response['addpaydata']['shipping_city'],
                'additionalAddressLine1' => $response['addpaydata']['shipping_addressaddition']
                    ?? null,
            ]),
        ]);
    }

    private function getSalutationId(Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('salutationKey', 'not_specified')
        );

        /** @var SalutationEntity|null $salutation */
        $salutation = $this->salutationRepository->search($criteria, $context)->first();

        if ($salutation === null) {
            throw new \RuntimeException($this->trans('PayonePayment.errorMessages.genericError'));
        }

        return $salutation->getId();
    }

    private function getCountryIdByCode(string $code, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('iso', $code)
        );

        /** @var CountryEntity|null $country */
        $country = $this->countryRepository->search($criteria, $context)->first();

        if (!$country instanceof CountryEntity) {
            return null;
        }

        return $country->getId();
    }

    private function handleStateResponse(string $state): void
    {
        if (empty($state)) {
            throw new \RuntimeException($this->trans('PayonePayment.errorMessages.genericError'));
        }

        if ($state === 'cancel') {
            throw new \RuntimeException($this->trans('PayonePayment.errorMessages.genericError'));
        }

        if ($state === 'error') {
            throw new \RuntimeException($this->trans('PayonePayment.errorMessages.genericError'));
        }
    }
}
