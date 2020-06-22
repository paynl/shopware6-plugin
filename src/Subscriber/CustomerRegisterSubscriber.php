<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Subscriber;

use PaynlPayment\Shopware6\Helper\CustomerHelper;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
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
        ];
    }

    /**
     * @param CustomerRegisterEvent $event
     */
    public function onCustomerRegister(CustomerRegisterEvent $event): void
    {
        $customer = $event->getCustomer();
        $addressId = $customer->getDefaultBillingAddressId();
        $request = $this->requestStack->getMasterRequest();
        $cocNumber = $request->get('coc_number');
        if(!is_null($cocNumber)) {
            $this->customerHelper->saveCocNumber($addressId, $cocNumber, $event->getContext());
        }
    }
}
