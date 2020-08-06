<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service;

use PaynlPayment\Shopware6\Helper\CustomerHelper;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\SalesChannel\AddressService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Shopware\Core\Framework\Validation\DataValidator;

class CustomerAddressService extends AddressService
{
    /**
     * @var EntityRepositoryInterface
     */
    private $customerAddressRepository;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var CustomerHelper
     */
    private $customerHelper;

    public function __construct(
        EntityRepositoryInterface $customerAddressRepository,
        DataValidationFactoryInterface $addressValidationFactory,
        DataValidator $validator,
        EventDispatcherInterface $eventDispatcher,
        SystemConfigService $systemConfigService,
        RequestStack $requestStack,
        CustomerHelper $customerHelper
    ) {
        parent::__construct(
            $customerAddressRepository,
            $addressValidationFactory,
            $validator,
            $eventDispatcher,
            $systemConfigService
        );

        $this->customerAddressRepository = $customerAddressRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->requestStack = $requestStack;
        $this->customerHelper = $customerHelper;
    }

    public function upsert(DataBag $data, SalesChannelContext $salesChannelContext): string
    {
        $addressId = parent::upsert($data, $salesChannelContext);
        $request = $this->requestStack->getMasterRequest();
        /** @var CustomerAddressEntity $customerAddress */
        $customerAddress = $salesChannelContext->getCustomer()->getDefaultBillingAddress();
        $cocNumber = $request->get('coc_number');
        if(!is_null($cocNumber) && ($customerAddress instanceof CustomerAddressEntity)) {
            $this->customerHelper->saveCocNumber($customerAddress, $cocNumber, $salesChannelContext->getContext());
        }

        return $addressId;
    }
}
