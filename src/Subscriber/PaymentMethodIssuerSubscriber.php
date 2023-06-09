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
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent;
use Symfony\Component\HttpFoundation\RequestStack;

class PaymentMethodIssuerSubscriber implements EventSubscriberInterface
{
    /** @var RequestStack */
    private $requestStack;

    /** @var EntityRepositoryInterface */
    private $paymentMethodRepository;

    /** @var CustomerHelper $customerHelper */
    private $customerHelper;

    /** @var RequestDataBagHelper $requestDataBagHelper */
    private $requestDataBagHelper;

    public function __construct(
        RequestStack $requestStack,
        EntityRepositoryInterface $paymentMethodRepository,
        CustomerHelper $customerHelper,
        RequestDataBagHelper $requestDataBagHelper
    ) {
        $this->requestStack = $requestStack;
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

        $paymentMethodId = $this->getValueFromRequestDataBag('paymentMethodId', $dataBag);
        $phoneData = (array) $this->getValueFromRequestDataBag('phone', $dataBag);
        $phone = $phoneData[$paymentMethodId] ?? null;
        if ($phone) {
            $billingAddress = $customer->getDefaultBillingAddress();
            $this->customerHelper->saveCustomerPhone($billingAddress, $phone, $context);
        }

        $dobData = (array) $this->getValueFromRequestDataBag('dob', $dataBag);
        $dob = $dobData[$paymentMethodId] ?? null;
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
            $this->requestStack->getSession()->remove('paynlIssuer');
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

    private function getValueFromRequestDataBag(string $name, RequestDataBag $dataBag)
    {
        $dataBagItem = $this->requestDataBagHelper->getDataBagItem($name, $dataBag);
        if ($dataBagItem instanceof RequestDataBag) {
            return $dataBagItem->all();
        }

        if (is_array($dataBagItem)) {
            return $dataBagItem;
        }

        return $dataBagItem;
    }
}
