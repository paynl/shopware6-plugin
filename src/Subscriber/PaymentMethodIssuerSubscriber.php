<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Subscriber;

use PaynlPayment\Shopware6\Helper\CustomerHelper;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerChangedPaymentMethodEvent;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent;

class PaymentMethodIssuerSubscriber implements EventSubscriberInterface
{
    /**
     * @var Session
     */
    private $session;

    /**
     * @var CustomerHelper
     */
    private $customerHelper;

    public function __construct(Session $session, CustomerHelper $customerHelper)
    {
        $this->session = $session;
        $this->customerHelper = $customerHelper;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent' => 'onCheckoutPaymentMethodChange',
            'Shopware\Core\Checkout\Customer\Event\CustomerChangedPaymentMethodEvent' => 'onPaymentMethodChanged'
        ];
    }

    /**
     * @param SalesChannelContextSwitchEvent $event
     */
    public function onCheckoutPaymentMethodChange(SalesChannelContextSwitchEvent $event)
    {

        if (array_key_exists('paynlIssuer', $event->getRequestDataBag()->all())) {
            $this->session->set('paynlIssuer', $event->getRequestDataBag()->get('paynlIssuer'));
        }

        $requestDataBagArray = $event->getRequestDataBag()->all();
        $customer = $event->getSalesChannelContext()->getCustomer();

        $this->processPayLaterFields($requestDataBagArray, $customer, $event->getContext());
    }

    /**
     * @param CustomerChangedPaymentMethodEvent $event
     */
    public function onPaymentMethodChanged(CustomerChangedPaymentMethodEvent $event)
    {
        $this->session->set('paynlIssuer', $event->getRequestDataBag()->get('paynlIssuer'));
        $requestDataBagArray = $event->getRequestDataBag()->all();
        $customer = $event->getSalesChannelContext()->getCustomer();

        $this->processPayLaterFields($requestDataBagArray, $customer, $event->getContext());
    }

    private function processPayLaterFields(array $requestData, CustomerEntity $customer, Context $context): void
    {
        $paymentMethodId = $requestData['paymentMethodId'];
        if (array_key_exists('phone', $requestData) && array_key_exists($paymentMethodId, $requestData['phone'])) {
            $phoneNumbers = $requestData['phone'];
            $phone = $phoneNumbers[$paymentMethodId];
            $billingAddress = $customer->getDefaultBillingAddress();

            $this->customerHelper->saveCustomerPhone($billingAddress, $phone, $context);
        }

        if (array_key_exists('dob', $requestData) && array_key_exists($paymentMethodId, $requestData['dob'])) {
            $dobArray = $requestData['dob'];
            $dob = $dobArray = $dobArray[$paymentMethodId] ?? '';

            $this->customerHelper->saveCustomerBirthdate($customer, $dob, $context);
        }
    }
}
