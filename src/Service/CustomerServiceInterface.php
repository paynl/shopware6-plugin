<?php
declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface CustomerServiceInterface
{
    public function customerLogin(CustomerEntity $customer, SalesChannelContext $context): ?string;
    public function isCustomerLoggedIn(SalesChannelContext $context): bool;

    public function getCustomer(string $customerId, Context $context): ?CustomerEntity;
    public function getCustomerByNumber(string $customerNumber, Context $context): ?CustomerEntity;

    /**
     * @param null|CustomerAddressEntity|OrderAddressEntity $address
     * @param CustomerEntity $customer
     * @return array<string, mixed>
     */
    public function getAddressArray($address, CustomerEntity $customer): array;
    public function updateCustomer(array $customerData, SalesChannelContext $salesChannelContext): ?CustomerEntity;
    public function createPaymentExpressCustomer(string $firstname, string $lastname, string $email, string $phone, string $street, string $zipCode, string $city, string $countryISO2, string $paymentMethodId, SalesChannelContext $context): ?CustomerEntity;
    public function getCountryId(string $countryCode, Context $context): ?string;
    public function getSalutationId(Context $context): ?string;
}
