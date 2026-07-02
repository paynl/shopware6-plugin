<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Controller\Storefront\AccountOrder;

use DateTime;
use Exception;
use PaynlPayment\Shopware6\Helper\CustomerHelper;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['storefront'], 'auth_required' => true, 'auth_enabled' => true])]
class AccountOrderController extends StorefrontController
{
    private CustomerHelper $customerHelper;

    public function __construct(CustomerHelper $customerHelper)
    {
        $this->customerHelper = $customerHelper;
    }

    #[Route('/PaynlPayment/order/change/paylater-fields', name: 'frontend.PaynlPayment.edit-order.change-paylater-fields', methods: ['POST'])]
    public function orderChangePayLaterFields(Request $request): JsonResponse
    {
        /** @var SalesChannelContext $salesChannelContext */
        $salesChannelContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        /** @var CustomerEntity|null $customer */
        $customer = $salesChannelContext->getCustomer();

        if (!$customer) {
            return new JsonResponse(['error' => 'Customer not found'], Response::HTTP_UNAUTHORIZED);
        }

        $dob = $request->request->get('dob');
        $phone = $request->request->get('phone');
        $context = $salesChannelContext->getContext();

        $errors = [];
        $updated = [];

        // Process date of birth
        if (!empty($dob)) {
            $dobError = $this->validateDateOfBirth($dob);
            if ($dobError) {
                $errors['dob'] = $dobError;
            } else {
                $updateError = $this->updateCustomerBirthdate($customer, $dob, $context);
                if ($updateError) {
                    $errors['dob'] = $updateError;
                } else {
                    $updated[] = 'dob';
                }
            }
        }

        // Process phone number
        if (!empty($phone)) {
            $phoneError = $this->validatePhoneNumber($phone);
            if ($phoneError) {
                $errors['phone'] = $phoneError;
            } else {
                $billingAddress = $customer->getDefaultBillingAddress();
                $updateError = $this->updateCustomerPhone($billingAddress, $phone, $context);
                if ($updateError) {
                    $errors['phone'] = $updateError;
                } else {
                    $updated[] = 'phone';
                }
            }
        }

        // Return response
        if (!empty($errors)) {
            return new JsonResponse([
                'success' => false,
                'errors' => $errors
            ], Response::HTTP_BAD_REQUEST);
        }

        if (empty($updated)) {
            return new JsonResponse([
                'success' => false,
                'error' => 'No fields provided'
            ], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'success' => true,
            'updated' => $updated
        ]);
    }

    private function validateDateOfBirth(string $dob): ?string
    {
        // Validate format
        $date = DateTime::createFromFormat('Y-m-d', $dob);
        if (!$date || $date->format('Y-m-d') !== $dob) {
            return 'Invalid date format. Expected: YYYY-MM-DD';
        }

        $now = new DateTime();

        // Check not in future
        if ($date > $now) {
            return 'Date of birth cannot be in the future';
        }

        // Calculate age
        $age = $date->diff($now)->y;

        // Check maximum age (reasonable limit)
        if ($age > 120) {
            return 'Invalid date of birth';
        }

        return null;
    }

    private function validatePhoneNumber(string $phone): ?string
    {
        $phone = trim($phone);

        // Check length
        if (strlen($phone) < 10) {
            return 'Phone number is too short (minimum 10 characters)';
        }

        if (strlen($phone) > 15) {
            return 'Phone number is too long (maximum 15 characters)';
        }

        // Check format: only digits, spaces, +, -, (, )
        if (!preg_match('/^[\d\s\-\+\(\)]+$/', $phone)) { //NOSONAR
            return 'Phone number contains invalid characters';
        }

        // Ensure at least 10 digits
        $digitCount = preg_match_all('/\d/', $phone);
        if ($digitCount < 10) {
            return 'Phone number must contain at least 10 digits';
        }

        return null;
    }

    private function updateCustomerBirthdate(
        CustomerEntity $customer,
        string $dob,
        Context $context
    ): ?string
    {
        try {
            $this->customerHelper->saveCustomerBirthdate($customer, $dob, $context);
            return null;
        } catch (Exception $e) {
            return 'Failed to update date of birth';
        }
    }

    private function updateCustomerPhone(
        ?CustomerAddressEntity $billingAddress,
        string $phone,
        Context $context
    ): ?string
    {
        if (!$billingAddress) {
            return 'Billing address not found';
        }

        try {
            $this->customerHelper->saveCustomerPhone($billingAddress, $phone, $context);

            return null;
        } catch (Exception $e) {
            return 'Failed to update phone number';
        }
    }
}