<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service;

use PaynlPayment\Shopware6\Helper\CustomerHelper;
use Shopware\Core\Checkout\Customer\SalesChannel\AddressService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

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
        EntityRepositoryInterface $countryRepository,
        EntityRepositoryInterface $customerAddressRepository,
        $addressValidationFactory,
        DataValidator $validator,
        EventDispatcherInterface $eventDispatcher,
        SystemConfigService $systemConfigService,
        RequestStack $requestStack,
        CustomerHelper $customerHelper
    ) {
        parent::__construct(
            $countryRepository,
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

    public function upsert(DataBag $data, SalesChannelContext $context): string
    {
        $addressId = parent::upsert($data, $context);
        $request = $this->requestStack->getMasterRequest();
        $cocNumber = $request->get('coc_number');
        if(!is_null($cocNumber)) {
            $this->customerHelper->saveCocNumber($addressId, $cocNumber, $context->getContext());
        }

        return $addressId;
    }

}