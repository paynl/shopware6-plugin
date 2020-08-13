<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Subscriber;

use PaynlPayment\Shopware6\Helper\CustomerHelper;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\DataMappingEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CustomerRegisterSubscriber implements EventSubscriberInterface
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerAddressRepository;

    /**
     * @var CustomerHelper
     */
    private $customerHelper;

    public function __construct(
        RequestStack $requestStack,
        EntityRepositoryInterface $customerAddressRepository,
        CustomerHelper $customerHelper
    ) {
        $this->requestStack = $requestStack;
        $this->customerAddressRepository = $customerAddressRepository;
        $this->customerHelper = $customerHelper;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent' => 'onCustomerRegister',
            'Shopware\Core\Checkout\Customer\Event\GuestCustomerRegisterEvent' => 'onCustomerRegister',
            CustomerEvents::MAPPING_ADDRESS_CREATE => 'onCustomerProfileSave',
        ];
    }

    /**
     * @param DataMappingEvent $event
     */
    public function onCustomerProfileSave(DataMappingEvent $event)
    {
        $request = $this->requestStack->getMasterRequest();
        $cocNumber = $request->get('coc_number');
        $addressId = $request->get('addressId');
        $context = $event->getContext();
        if (is_null($addressId)) {
            return;
        }
        /** @var CustomerAddressEntity $customerAddress */
        $customerAddress = $this->customerAddressRepository->search(new Criteria([$addressId]), $context)->first();
        if(!is_null($cocNumber) && ($customerAddress instanceof CustomerAddressEntity)) {
            $this->customerHelper->saveCocNumber($customerAddress, $cocNumber, $event->getContext());
        }
    }

    /**
     * @param CustomerRegisterEvent $event
     */
    public function onCustomerRegister(CustomerRegisterEvent $event): void
    {
        $customer = $event->getCustomer();
        $addressId = $customer->getDefaultBillingAddressId();
        $request = $this->requestStack->getMasterRequest();
        /** @var CustomerAddressEntity $customerAddress */
        $customerAddress = $customer->getAddresses()->filterByProperty('id', $addressId)->first();
        $cocNumber = $request->get('coc_number');
        if(!is_null($cocNumber) && ($customerAddress instanceof CustomerAddressEntity)) {
            $this->customerHelper->saveCocNumber($customerAddress, $cocNumber, $event->getContext());
        }
    }
}
