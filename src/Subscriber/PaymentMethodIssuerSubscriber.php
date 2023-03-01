<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Subscriber;

use PaynlPayment\Shopware6\Helper\CustomerHelper;
use PaynlPayment\Shopware6\Helper\RequestDataBagHelper;
use PaynlPayment\Shopware6\Service\PaymentMethodCustomFields;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerChangedPaymentMethodEvent;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent;

class PaymentMethodIssuerSubscriber implements EventSubscriberInterface
{
    /** @var Session $session */
    private $session;

    /** @var EntityRepositoryInterface */
    private $paymentMethodRepository;

    /** @var CustomerHelper $customerHelper */
    private $customerHelper;

    /** @var RequestDataBagHelper $requestDataBagHelper */
    private $requestDataBagHelper;

    public function __construct(
        Session $session,
        EntityRepositoryInterface $paymentMethodRepository,
        CustomerHelper $customerHelper,
        RequestDataBagHelper $requestDataBagHelper
    ) {
        $this->session = $session;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->customerHelper = $customerHelper;
        $this->requestDataBagHelper = $requestDataBagHelper;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent' => 'onCheckoutPaymentMethodChange',
            'Shopware\Core\Checkout\Customer\Event\CustomerChangedPaymentMethodEvent' => 'onPaymentMethodChanged',
        ];
    }

    /**
     * @param SalesChannelContextSwitchEvent $event
     */
    public function onCheckoutPaymentMethodChange(SalesChannelContextSwitchEvent $event)
    {
        if (!$event->getRequestDataBag()->has('paymentMethodId')) {
            return;
        }
        $customer = $event->getSalesChannelContext()->getCustomer();

        $this->processPayLaterFields($event->getRequestDataBag()->toRequestDataBag(), $customer, $event->getContext());
    }

    /**
     * @param CustomerChangedPaymentMethodEvent $event
     */
    public function onPaymentMethodChanged(CustomerChangedPaymentMethodEvent $event)
    {
        if (!$event->getRequestDataBag()->has('paymentMethodId')) {
            return;
        }
        $customer = $event->getSalesChannelContext()->getCustomer();

        $this->processPayLaterFields($event->getRequestDataBag(), $customer, $event->getContext());
    }

    private function processPayLaterFields(RequestDataBag $dataBag, ?CustomerEntity $customer, Context $context): void
    {
        if (!($customer instanceof CustomerEntity)) {
            return;
        }

        $paymentMethodId = $this->requestDataBagHelper->getDataBagItem('paymentMethodId', $dataBag);
        $phoneDataBag = $this->requestDataBagHelper->getDataBagItem('phone', $dataBag);
        $phone = $phoneDataBag instanceof RequestDataBag ? $phoneDataBag->get($paymentMethodId) : null;
        if ($phone) {
            $billingAddress = $customer->getDefaultBillingAddress();
            $this->customerHelper->saveCustomerPhone($billingAddress, $phone, $context);
        }

        $dobDataBag = $this->requestDataBagHelper->getDataBagItem('dob', $dataBag);
        $dob = $dobDataBag instanceof RequestDataBag ? $dobDataBag->get($paymentMethodId) : null;
        if ($dob) {
            $this->customerHelper->saveCustomerBirthdate($customer, $dob, $context);
        }

        $this->savePaynlPaymentMethodIssuer($dataBag, $customer, $context);
    }

    private function savePaynlPaymentMethodIssuer(
        RequestDataBag $dataBag,
        CustomerEntity $customer,
        Context $context
    ): void {
        $paymentMethodId = (string) $this->requestDataBagHelper->getDataBagItem('paymentMethodId', $dataBag);
        $paymentMethod = $this->getPaymentMethodById($paymentMethodId, $context);

        if (empty($paymentMethod)) {
            return;
        }

        $paymentMethodCustomFields = $paymentMethod->getTranslation('customFields');
        $paymentMethodDisplayBanks = $paymentMethodCustomFields[PaymentMethodCustomFields::DISPLAY_BANKS_FIELD] ?? null;
        if (!$paymentMethodDisplayBanks) {
            $this->session->remove('paynlIssuer');
            return;
        }

        $paynlIssuer = (string) $this->requestDataBagHelper->getDataBagItem('paynlIssuer', $dataBag);

        $this->customerHelper->savePaynlIssuer($customer, $paymentMethodId, $paynlIssuer, $context);
    }

    private function getPaymentMethodById(string $paymentMethodId, Context $context): ?PaymentMethodEntity
    {
        /** @var PaymentMethodEntity $paymentMethod */
        return $this->paymentMethodRepository->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('id', $paymentMethodId)),
            $context
        )->first();
    }
}
