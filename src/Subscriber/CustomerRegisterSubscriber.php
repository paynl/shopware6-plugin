<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Subscriber;

use PaynlPayment\Shopware6\Helper\CustomerHelper;
use PaynlPayment\Shopware6\Repository\Customer\CustomerRepositoryInterface;
use PaynlPayment\Shopware6\Repository\CustomerAddress\CustomerAddressRepositoryInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CustomerRegisterSubscriber implements EventSubscriberInterface
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var CustomerAddressRepositoryInterface
     */
    private $customerAddressRepository;

    /**
     * @var CustomerRepositoryInterface
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
        CustomerAddressRepositoryInterface $customerAddressRepository,
        CustomerRepositoryInterface $customerRepository,
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
            $request = $this->requestStack->getCurrentRequest();
            if (is_null($request)) {
                return;
            }
            $cocNumber = $request->request->getString('coc_number');
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
            $request = $this->requestStack->getCurrentRequest();
            if (is_null($request)) {
                return;
            }
            $cocNumber = $request->request->getString('coc_number');
            $context = $event->getContext();
            $customerCriteria = new Criteria();
            $payloads = $event->getPayloads();
            $customerId = $payloads[0]['id'] ?? null;
            if (is_null($customerId)) {
                return;
            }
            $customerCriteria->addFilter(new EqualsFilter('id', $customerId));
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
