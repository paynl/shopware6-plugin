<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\AccountOrder;

use PaynlPayment\Shopware6\Helper\CustomerHelper;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class AccountOrderControllerBase extends StorefrontController
{
    /**
     * @var CustomerHelper
     */
    private $customerHelper;

    public function __construct(CustomerHelper $customerHelper)
    {
        $this->customerHelper = $customerHelper;
    }

    /**
     * @Route(
     *     "/PaynlPayment/order/change/paylater-fields",
     *     name="frontend.PaynlPayment.edit-order.change-paylater-fields",
     *     methods={"POST"},
     *     defaults={"csrf_protected"=false, "_routeScope"={"storefront"}},
     *     )
     * @param Request $request
     * @return JsonResponse
     */
    public function orderChangePaylaterFields(Request $request): JsonResponse
    {
        /** @var SalesChannelContext $salesChannelContext */
        $salesChannelContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        $dob = $request->get('dob');
        $phone = $request->get('phone');
        /** @var CustomerEntity $customer */
        $customer = $salesChannelContext->getCustomer();
        /** @var CustomerAddressEntity $billingAddress */
        $billingAddress = $customer->getDefaultBillingAddress();
        $context = $salesChannelContext->getContext();
        if (!empty($dob)) {
            $this->customerHelper->saveCustomerBirthdate($customer, $dob, $context);
        }

        if (!empty($phone)) {
            $this->customerHelper->saveCustomerPhone($billingAddress, $phone, $context);
        }

        return new JsonResponse(['success' => true]);
    }
}