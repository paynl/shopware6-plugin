<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Subscriber;

use PaynlPayment\Shopware6\Helper\CustomerHelper;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
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
     * @var EntityRepositoryInterface
     */
    private $customerAddressRepository;

    /**
     * @var CustomerHelper
     */
    private $customerHelper;

    /** @var int $counter */
    private $counter = 0;

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
            CustomerEvents::CUSTOMER_ADDRESS_WRITTEN_EVENT => 'onCustomerAddressChanged',
            CustomerEvents::CUSTOMER_WRITTEN_EVENT => 'onCustomerProfileChanged',
        ];
    }

    public function onCustomerAddressChanged(EntityWrittenEvent $event)
    {
        if ($this->counter < 1) {
            $this->counter++;
            $request = $this->requestStack->getMasterRequest();
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
            $cocNumber = $request->get('coc_number');
            $context = $event->getContext();
            $criteria = new Criteria();
            $payloads = $event->getPayloads();
            $criteria->addFilter(new EqualsFilter('customerId', $payloads[0]['id']));

            $this->updateCustomerCocNumber($cocNumber, $criteria, $context);
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
