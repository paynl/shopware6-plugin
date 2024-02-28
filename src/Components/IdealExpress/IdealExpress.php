<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Components\IdealExpress;

use PaynlPayment\Shopware6\Components\IdealExpress\Services\IdealExpressShippingBuilder;
use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Repository\Order\OrderAddressRepositoryInterface;
use PaynlPayment\Shopware6\Repository\PaymentMethod\PaymentMethodRepository;
use PaynlPayment\Shopware6\Service\Cart\CartBackupService;
use PaynlPayment\Shopware6\Service\CartServiceInterface;
use PaynlPayment\Shopware6\Service\CustomerService;
use PaynlPayment\Shopware6\Service\OrderService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class IdealExpress
{
    /**
     * @var CartServiceInterface
     */
    private $cartService;

    /**
     * @var IdealExpressShippingBuilder
     */
    private $shippingBuilder;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var CustomerService
     */
    private $customerService;

    /**
     * @var PaymentMethodRepository
     */
    private $repoPaymentMethods;

    /**
     * @var CartBackupService
     */
    private $cartBackupService;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var OrderAddressRepositoryInterface
     */
    private $repoOrderAdresses;

    public function __construct(CartServiceInterface $cartService, IdealExpressShippingBuilder $shippingBuilder, Config $config, CustomerService $customerService, PaymentMethodRepository $repoPaymentMethods, CartBackupService $cartBackupService, OrderService $orderService, OrderAddressRepositoryInterface $repoOrderAdresses)
    {
        $this->cartService = $cartService;
        $this->shippingBuilder = $shippingBuilder;
        $this->config = $config;
        $this->customerService = $customerService;
        $this->repoPaymentMethods = $repoPaymentMethods;
        $this->cartBackupService = $cartBackupService;
        $this->orderService = $orderService;
        $this->repoOrderAdresses = $repoOrderAdresses;
    }

    public function getActiveIdealID(SalesChannelContext $context): string
    {
        return $this->repoPaymentMethods->getActiveIdealID($context->getContext());
    }

    public function isIdealExpressEnabled(SalesChannelContext $context): bool
    {
        return true;

        $isIdealExpressEnabled = true;

        /** @var null|array<mixed> $salesChannelPaymentIDs */
        $salesChannelPaymentIDs = $context->getSalesChannel()->getPaymentMethodIds();

        $enabled = false;

        if (is_array($salesChannelPaymentIDs) && $isIdealExpressEnabled) {
            try {
                $idealExpressID = $this->repoPaymentMethods->getActiveIdealID($context->getContext());

                foreach ($salesChannelPaymentIDs as $tempID) {
                    # verify if our IDEAL Express payment method is indeed in use
                    # for the current sales channel
                    if ($tempID === $idealExpressID) {
                        $enabled = true;
                        break;
                    }
                }
            } catch (\Exception $ex) {
                # it can happen that IDEAL Express is just not active in the system
            }
        }

        return $enabled;
    }

    public function addProduct(string $productId, int $quantity, SalesChannelContext $context): Cart
    {
        # if we already have a backup cart, then do NOT backup again.
        # because this could backup our temp. IDEAL Express cart
        if (!$this->cartBackupService->isBackupExisting($context)) {
            $this->cartBackupService->backupCart($context);
        }

        $cart = $this->cartService->getCalculatedMainCart($context);

        # clear existing cart and also update it to save it
        $cart->setLineItems(new LineItemCollection());
        $this->cartService->updateCart($cart);

        # add new product to cart
        $this->cartService->addProduct($productId, $quantity, $context);

        return $this->cartService->getCalculatedMainCart($context);
    }

    public function setShippingMethod(string $shippingMethodID, SalesChannelContext $context): SalesChannelContext
    {
        return $this->cartService->updateShippingMethod($context, $shippingMethodID);
    }

    public function getShippingMethods(string $countryCode, SalesChannelContext $context): array
    {
        $currentMethodID = $context->getShippingMethod()->getId();

        $countryID = (string)$this->customerService->getCountryId($countryCode, $context->getContext());

        # get all available shipping methods of
        # our current country for IDEAL Express
        $shippingMethods = $this->shippingBuilder->getShippingMethods($countryID, $context);

        # restore our previously used shipping method
        # this is very important to avoid accidental changes in the context
        $this->cartService->updateShippingMethod($context, $currentMethodID);

        return $shippingMethods;
    }

    /**
     * @param SalesChannelContext $context
     */
    public function restoreCart(SalesChannelContext $context): void
    {
        if ($this->cartBackupService->isBackupExisting($context)) {
            $this->cartBackupService->restoreCart($context);
        }

        $this->cartBackupService->clearBackup($context);
    }

    public function prepareCustomer(
        string $firstname,
        string $lastname,
        string $email,
        string $street,
        string $zipcode,
        string $city,
        string $countryCode,
        SalesChannelContext $context
    ): SalesChannelContext {
        # we clear our cart backup now
        # we are in the user redirection process where a restoring wouldn't make sense
        # because from now on we would end on the cart page where we could even switch payment method.
        $this->cartBackupService->clearBackup($context);


        $idealExpressID = $this->getActiveIdealID($context);

        # if we are not logged in,
        # then we have to create a new guest customer for our express order
        if (!$this->customerService->isCustomerLoggedIn($context)) {
            $customer = $this->customerService->createIdealExpressCustomer(
                $firstname,
                $lastname,
                $email,
                '',
                $street,
                $zipcode,
                $city,
                $countryCode,
                $idealExpressID,
                $context
            );

            if (!$customer instanceof CustomerEntity) {
                throw new \Exception('Error when creating customer!');
            }

            # now start the login of our customer.
            # Our SalesChannelContext will be correctly updated after our
            # forward to the finish-payment page.
            $this->customerService->customerLogin($customer, $context);
        }

        # also (always) update our payment method to use IDEAL Express for our cart
        return $this->cartService->updatePaymentMethod($context, $idealExpressID);
    }

    /**
     * @param SalesChannelContext $context
     * @return OrderEntity
     */
    public function createOrder(SalesChannelContext $context): OrderEntity
    {
        $data = new DataBag();

        # we have to agree to the terms of services
        # to avoid constraint violation checks
        $data->add(['tos' => true]);

        # create our new Order using the
        # Shopware function for it.
        return $this->orderService->createOrder($data, $context);
    }

    public function createPayment(
        OrderEntity $order,
        string $shopwareReturnUrl,
        string $firstname,
        string $lastname,
        string $street,
        string $zipcode,
        string $city,
        string $countryCode,
        SalesChannelContext $context
    ): string {
        # immediately try to get the country of the buyer.
        # maybe this could lead to an exception if that country is not possible.
        # that's why we do it within these first steps.
        $countryID = (string)$this->customerService->getCountryId($countryCode, $context->getContext());


        # always make sure to use the correct address from IDEAL Express
        # and never the one from the customer (if already existing)
        if ($order->getAddresses() instanceof OrderAddressCollection) {
            foreach ($order->getAddresses() as $address) {
                # attention, IDEAL Express does not have a company name
                # therefore we always need to make sure to remove the company field in our order
                $this->repoOrderAdresses->updateAddress(
                    $address->getId(),
                    $firstname,
                    $lastname,
                    '',
                    '',
                    '',
                    $street,
                    $zipcode,
                    $city,
                    $countryID,
                    $context->getContext()
                );
            }
        }


        # get the latest new transaction.
        # we need this for our payment handler
        /** @var OrderTransactionCollection $transactions */
        $transactions = $order->getTransactions();
        $transaction = $transactions->last();

        if (!$transaction instanceof OrderTransactionEntity) {
            throw new \Exception('Created IDEAL Express Direct order has not OrderTransaction!');
        }

        # generate the finish URL for our shopware page.
        # This is required, because we will immediately bring the user to this page.
        $asyncPaymentTransition = new AsyncPaymentTransactionStruct($transaction, $order, $shopwareReturnUrl);

        # now set the IDEAL Express payment token for our payment handler.
        # This is required for a smooth checkout with our already validated IDEAL Express transaction.
//        $this->paymentHandler->setToken($paymentToken);

//        $paymentData = $this->molliePayments->startMolliePayment(ApplePayPayment::PAYMENT_METHOD_NAME, $asyncPaymentTransition, $context, $this->paymentHandler);

//        if (empty($paymentData->getCheckoutURL())) {
//            throw new \Exception('Error when creating IDEAL Express Direct order in Mollie');
//        }


        # now also update the custom fields of our order
        # we want to have the mollie metadata in the
        # custom fields in Shopware too
//        $this->orderService->updateMollieDataCustomFields(
//            $order,
//            $paymentData->getMollieID(),
//            '',
//            $transaction->getId(),
//            $context->getContext()
//        );


//        return $paymentData->getMollieID();
        return '';
    }
}
