<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Subscriber;

use PaynlPayment\Shopware6\Helper\CustomerHelper;
use PaynlPayment\Shopware6\Service\PaymentMethodCustomFields;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerChangedPaymentMethodEvent;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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

    public function __construct(
        Session $session,
        EntityRepositoryInterface $paymentMethodRepository,
        CustomerHelper $customerHelper
    ) {
        $this->session = $session;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->customerHelper = $customerHelper;
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
        $requestDataBagArray = $event->getRequestDataBag()->all();
        $customer = $event->getSalesChannelContext()->getCustomer();

        $this->processPayLaterFields($requestDataBagArray, $customer, $event->getContext());
    }

    /**
     * @param CustomerChangedPaymentMethodEvent $event
     */
    public function onPaymentMethodChanged(CustomerChangedPaymentMethodEvent $event)
    {
        if (!$event->getRequestDataBag()->has('paymentMethodId')) {
            return;
        }
        $requestDataBagArray = $event->getRequestDataBag()->all();
        $customer = $event->getSalesChannelContext()->getCustomer();

        $this->processPayLaterFields($requestDataBagArray, $customer, $event->getContext());
    }

    private function processPayLaterFields(array $requestData, ?CustomerEntity $customer, Context $context): void
    {
        if (!($customer instanceof CustomerEntity)) {
            return;
        }

        $paymentMethodId = $requestData['paymentMethodId'];
        $phone = $requestData['phone'][$paymentMethodId] ?? null;
        if ($phone) {
            $billingAddress = $customer->getDefaultBillingAddress();
            $this->customerHelper->saveCustomerPhone($billingAddress, $phone, $context);
        }
        $dob = $requestData['dob'][$paymentMethodId] ?? null;
        if ($dob) {
            $this->customerHelper->saveCustomerBirthdate($customer, $dob, $context);
        }

        $this->savePaynlPaymentMethodIssuer($requestData, $customer, $context);
    }

    private function savePaynlPaymentMethodIssuer(array $requestData, CustomerEntity $customer, Context $context): void
    {
        $paymentMethod = $this->getPaymentMethodById($requestData['paymentMethodId'], $context);

        if (empty($paymentMethod)) {
            return;
        }

        $paymentMethodCustomFields = $paymentMethod->getTranslation('customFields');
        $paymentMethodDisplayBanks = $paymentMethodCustomFields[PaymentMethodCustomFields::DISPLAY_BANKS_FIELD] ?? null;
        if (!$paymentMethodDisplayBanks) {
            $this->session->remove('paynlIssuer');
            return;
        }

        $paymentMethodId = (string)$requestData['paymentMethodId'];
        $paynlIssuer = (string)($requestData['paynlIssuer'] ?? '');

        $this->customerHelper->savePaynlIssuer($customer, $paymentMethodId, $paynlIssuer, $context);
    }

    private function getPaymentMethodById(string $paymentMethodId, Context $context): ?PaymentMethodEntity
    {
        /** @var PaymentMethodEntity $paymentMethod */
        $paymentMethod = $this->paymentMethodRepository->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('id', $paymentMethodId)),
            $context
        )->first();

        return $paymentMethod;
    }
}
