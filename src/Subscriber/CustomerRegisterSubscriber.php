<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Subscriber;

use PaynlPayment\Shopware6\Helper\CustomerHelper;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
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
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CustomerHelper
     */
    private $customerHelper;

    /** @var int $counter */
    private $counter = 0;

    public function __construct(
        RequestStack $requestStack,
        EntityRepositoryInterface $customerAddressRepository,
        EntityRepositoryInterface $customerRepository,
        CustomerHelper $customerHelper
    ) {
        $this->requestStack = $requestStack;
        $this->customerAddressRepository = $customerAddressRepository;
        $this->customerRepository = $customerRepository;
        $this->customerHelper = $customerHelper;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerEvents::CUSTOMER_ADDRESS_WRITTEN_EVENT => 'onCustomerAddressChanged',
            CustomerEvents::CUSTOMER_WRITTEN_EVENT => 'onCustomerProfileChanged',
        ];
    }

    public function onCustomerAddressChanged(EntityWrittenEvent $event)
    {
        if ($this->counter < 1) {
            $this->counter++;
            $request = $this->requestStack->getMasterRequest();
            if (is_null($request)) {
                return;
            }
            $cocNumber = $request->get('coc_number');
            $addressIdArray = $event->getIds();
            $context = $event->getContext();
            $criteria = new Criteria($addressIdArray);

            $this->updateCustomerCocNumber($cocNumber, $criteria, $context);
        }

        return;
    }

    public function onCustomerProfileChanged(EntityWrittenEvent $event)
    {
        if ($this->counter < 1) {
            $this->counter++;
            $request = $this->requestStack->getMasterRequest();
            if (is_null($request)) {
                return;
            }
            $cocNumber = $request->get('coc_number');
            $context = $event->getContext();
            $customerCriteria = new Criteria();
            $payloads = $event->getPayloads();
            $customerCriteria->addFilter(new EqualsFilter('id', $payloads[0]['id']));
            /** @var CustomerEntity $customerAddress */
            $customer = $this->customerRepository->search($customerCriteria, $context)->first();
            if ($customer instanceof CustomerEntity) {
                $criteria = new Criteria();
                $criteria->addFilter(new EqualsFilter('id', $customer->getDefaultBillingAddressId()));

                $this->updateCustomerCocNumber($cocNumber, $criteria, $context);
            }
        }

        return;
    }

    private function updateCustomerCocNumber(?string $cocNumber, Criteria $criteria, Context $context)
    {
        /** @var CustomerAddressEntity $customerAddress */
        $customerAddress = $this->customerAddressRepository->search($criteria, $context)->first();

        if ($customerAddress !== null) {
            if(!is_null($cocNumber) && ($customerAddress instanceof CustomerAddressEntity)) {
                $this->customerHelper->saveCocNumber($customerAddress, $cocNumber, $context);
            }
        }
    }
}
