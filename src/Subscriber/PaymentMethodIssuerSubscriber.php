<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Subscriber;

use PaynlPayment\Shopware6\Helper\CustomerHelper;
use PaynlPayment\Shopware6\Helper\RequestDataBagHelper;
use PaynlPayment\Shopware6\Repository\PaymentMethod\PaymentMethodRepositoryInterface;
use PaynlPayment\Shopware6\Service\PaymentMethod\PaymentMethodIssuerService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerChangedPaymentMethodEvent;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PaymentMethodIssuerSubscriber implements EventSubscriberInterface
{
    /** @var PaymentMethodRepositoryInterface */
    private $paymentMethodRepository;

    /** @var CustomerHelper $customerHelper */
    private $customerHelper;

    /** @var RequestDataBagHelper $requestDataBagHelper */
    private $requestDataBagHelper;

    /** @var PaymentMethodIssuerService */
    private $issuerService;

    public function __construct(
        PaymentMethodRepositoryInterface $paymentMethodRepository,
        CustomerHelper $customerHelper,
        RequestDataBagHelper $requestDataBagHelper,
        PaymentMethodIssuerService $issuerService
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->customerHelper = $customerHelper;
        $this->requestDataBagHelper = $requestDataBagHelper;
        $this->issuerService = $issuerService;
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
        $this->processPayLaterFields($event->getRequestDataBag()->toRequestDataBag(), $event->getSalesChannelContext());
    }

    /**
     * @param CustomerChangedPaymentMethodEvent $event
     */
    public function onPaymentMethodChanged(CustomerChangedPaymentMethodEvent $event)
    {
        if (!$event->getRequestDataBag()->has('paymentMethodId')) {
            return;
        }
        $this->processPayLaterFields($event->getRequestDataBag(), $event->getSalesChannelContext());
    }

    private function processPayLaterFields(RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): void
    {
        $customer = $salesChannelContext->getCustomer();
        if (!($customer instanceof CustomerEntity)) {
            return;
        }

        $context = $salesChannelContext->getContext();

        $paymentMethodId = $this->getValueFromRequestDataBag('paymentMethodId', $dataBag);
        $phoneData = (array) $this->getArrayFromRequestDataBag('phone', $dataBag);
        $phone = $phoneData[$paymentMethodId] ?? null;
        if ($phone) {
            $billingAddress = $customer->getDefaultBillingAddress();
            $this->customerHelper->saveCustomerPhone($billingAddress, $phone, $context);
        }

        $dobData = (array) $this->getArrayFromRequestDataBag('dob', $dataBag);
        $dob = $dobData[$paymentMethodId] ?? null;
        if ($dob) {
            $this->customerHelper->saveCustomerBirthdate($customer, $dob, $context);
        }

        $this->savePaynlPaymentMethodIssuer($dataBag, $customer, $salesChannelContext);
    }

    private function savePaynlPaymentMethodIssuer(
        RequestDataBag $dataBag,
        CustomerEntity $customer,
        SalesChannelContext $salesChannelContext
    ): void {
        // Not submitted: keep the bank the customer saved earlier
        if (!$this->requestDataBagHelper->hasDataBagItem('paynlIssuer', $dataBag)) {
            return;
        }

        $context = $salesChannelContext->getContext();
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();
        $paymentMethodId = (string) $this->requestDataBagHelper->getDataBagItem('paymentMethodId', $dataBag);
        $paymentMethod = $this->getPaymentMethodById($paymentMethodId, $context);

        if (empty($paymentMethod) || !$this->issuerService->isBankListEnabled($paymentMethod, $salesChannelId)) {
            return;
        }

        $requestedIssuer = trim((string) $this->requestDataBagHelper->getDataBagItem('paynlIssuer', $dataBag));

        // The empty "Select issuer" option removes the saved bank
        if ($requestedIssuer === '') {
            $this->customerHelper->clearPaynlIssuer($customer, $paymentMethodId, $context);
            return;
        }

        $issuerId = $this->issuerService->resolveIssuer($paymentMethod, $requestedIssuer, $salesChannelId);

        if ($issuerId !== null) {
            $this->customerHelper->savePaynlIssuer($customer, $paymentMethodId, $issuerId, $context);
        }
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


    private function getArrayFromRequestDataBag(string $name, RequestDataBag $dataBag)
    {
        $dataBagArray = $this->requestDataBagHelper->getDataBagArray($name, $dataBag);
        if ($dataBagArray instanceof RequestDataBag) {
            return $dataBagArray->all();
        }

        return $dataBagArray;
    }
}
