<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service;

use Exception;
use PaynlPayment\Shopware6\Repository\Country\CountryRepositoryInterface;
use PaynlPayment\Shopware6\Repository\Customer\CustomerRepositoryInterface;
use PaynlPayment\Shopware6\Repository\Salutation\SalutationRepositoryInterface;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CustomerService implements CustomerServiceInterface
{
    /** @var CountryRepositoryInterface */
    private $countryRepository;

    /** @var CustomerRepositoryInterface */
    private $customerRepository;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /** @var SalesChannelContextPersister */
    private $salesChannelContextPersister;

    /** @var SalutationRepositoryInterface */
    private $salutationRepository;

    /** @var string */
    private $shopwareVersion;

    /** @var NumberRangeValueGeneratorInterface */
    private $valueGenerator;

    public function __construct(
        CountryRepositoryInterface $countryRepository,
        CustomerRepositoryInterface $customerRepository,
        EventDispatcherInterface $eventDispatcher,
        SalesChannelContextPersister $salesChannelContextPersister,
        SalutationRepositoryInterface $salutationRepository,
        string $shopwareVersion,
        NumberRangeValueGeneratorInterface $valueGenerator
    ) {
        $this->countryRepository = $countryRepository;
        $this->customerRepository = $customerRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->salesChannelContextPersister = $salesChannelContextPersister;
        $this->salutationRepository = $salutationRepository;
        $this->shopwareVersion = $shopwareVersion;
        $this->valueGenerator = $valueGenerator;
    }

    /**
     * Login the customer.
     *
     * @param CustomerEntity $customer
     * @param SalesChannelContext $context
     *
     * @return null|string
     */
    public function customerLogin(CustomerEntity $customer, SalesChannelContext $context): ?string
    {
        /** @var null|string $newToken */
        $newToken = null;

        /** @var CustomerBeforeLoginEvent $event */
        $event = new CustomerBeforeLoginEvent($context, $customer->getEmail());

        // Dispatch the before login event
        $this->eventDispatcher->dispatch($event);

        /** @var string $newToken */
        $newToken = $this->salesChannelContextPersister->replace($context->getToken(), $context);

        // Persist the new token
        if (version_compare($this->shopwareVersion, '6.3.3', '<')) {
            // Shopware 6.3.2.x and lower
            $params = [
                'customerId' => $customer->getId(),
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ];

            /** @phpstan-ignore-next-line */
            $this->salesChannelContextPersister->save($newToken, $params);
        } elseif (version_compare($this->shopwareVersion, '6.3.4', '<') && version_compare($this->shopwareVersion, '6.3.3', '>=')) {
            // Shopware 6.3.3.x
            $this->salesChannelContextPersister->save(
                $newToken,
                [
                    'customerId' => $customer->getId(),
                    'billingAddressId' => null,
                    'shippingAddressId' => null,
                ],
                $customer->getId()
            );
        } else {
            // Shopware 6.3.4+
            $this->salesChannelContextPersister->save(
                $newToken,
                [
                    'customerId' => $customer->getId(),
                    'billingAddressId' => null,
                    'shippingAddressId' => null,
                ],
                $context->getSalesChannel()->getId(),
                $customer->getId()
            );
        }

        /** @var CustomerLoginEvent $event */
        $event = new CustomerLoginEvent($context, $customer, $newToken);

        // Dispatch the customer login event
        $this->eventDispatcher->dispatch($event);

        return $newToken;
    }

    /**
     * @param SalesChannelContext $context
     * @return bool
     */
    public function isCustomerLoggedIn(SalesChannelContext $context): bool
    {
        return ($context->getCustomer() instanceof CustomerEntity);
    }

    /**
     * Return a customer entity with address associations.
     *
     * @param string $customerId
     * @param Context $context
     * @return null|CustomerEntity
     */
    public function getCustomer(string $customerId, Context $context): ?CustomerEntity
    {
        $customer = null;

        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('id', $customerId));
            $criteria->addAssociations([
                'defaultShippingAddress.country',
                'defaultBillingAddress.country',
            ]);

            /** @var CustomerEntity $customer */
            $customer = $this->customerRepository->search($criteria, $context)->first();
        } catch (\Exception $e) {
            // Should error be (re)thrown here, instead of returning null?
        }

        return $customer;
    }

    public function getCustomerByNumber(string $customerNumber, Context $context): ?CustomerEntity
    {
        $customer = null;

        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('customerNumber', $customerNumber));
            $criteria->addAssociations([
                'defaultShippingAddress.country',
                'defaultBillingAddress.country',
            ]);

            /** @var CustomerEntity $customer */
            $customer = $this->customerRepository->search($criteria, $context)->first();
        } catch (\Exception $e) {
            // Should error be (re)thrown here, instead of returning null?
        }

        return $customer;
    }

    /**
     * Return an array of address data.
     *
     * @param null|CustomerAddressEntity|OrderAddressEntity $address
     * @param CustomerEntity $customer
     * @return array<mixed>
     */
    public function getAddressArray($address, CustomerEntity $customer): array
    {
        if ($address === null) {
            return [];
        }

        return [
            'title' => $address->getSalutation() !== null ? $address->getSalutation()->getDisplayName() . '.' : null,
            'givenName' => $address->getFirstName(),
            'familyName' => $address->getLastName(),
            'email' => $customer->getEmail(),
            'streetAndNumber' => $address->getStreet(),
            'streetAdditional' => $address->getAdditionalAddressLine1(),
            'postalCode' => $address->getZipCode(),
            'city' => $address->getCity(),
            'country' => $address->getCountry() !== null ? $address->getCountry()->getIso() : 'NL',
        ];
    }

    public function createPaymentExpressCustomer(string $firstname, string $lastname, string $email, string $phone, string $street, string $zipCode, string $city, string $countryISO2, string $paymentMethodId, SalesChannelContext $context): ?CustomerEntity
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $salutationId = $this->getSalutationId($context->getContext());
        $countryId = $this->getCountryId($countryISO2, $context->getContext());

        $customerNumber = $this->valueGenerator->getValue(
            'customer',
            $context->getContext(),
            $context->getSalesChannelId()
        );

        $customer = [
            'id' => $customerId,
            'salutationId' => $salutationId,
            'firstName' => $firstname,
            'lastName' => $lastname,
            'customerNumber' => $customerNumber,
            'guest' => true,
            'email' => $email,
            'password' => Uuid::randomHex(),
            'defaultPaymentMethodId' => $paymentMethodId,
            'groupId' => $context->getSalesChannel()->getCustomerGroupId(),
            'salesChannelId' => $context->getSalesChannel()->getId(),
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $countryId,
                    'salutationId' => $salutationId,
                    'firstName' => $firstname,
                    'lastName' => $lastname,
                    'street' => $street,
                    'zipcode' => $zipCode,
                    'city' => $city,
                    'phoneNumber' => $phone,
                ],
            ],
        ];

        // Add the customer to the database
        $this->customerRepository->upsert([$customer], $context->getContext());

        return $this->getCustomer($customerId, $context->getContext());
    }

    public function updateCustomer(array $customerData, SalesChannelContext $salesChannelContext): ?CustomerEntity
    {
        $this->customerRepository->update([$customerData], $salesChannelContext->getContext());

        return $this->getCustomer($customerData['id'], $salesChannelContext->getContext());
    }

    /**
     * Returns a country id by its iso code.
     *
     * @param string $countryCode
     * @param Context $context
     *
     * @return null|string
     */
    public function getCountryId(string $countryCode, Context $context): ?string
    {
        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('iso', strtoupper($countryCode)));

            // Get countries
            /** @var string[] $countries */
            $countries = $this->countryRepository->searchIds($criteria, $context)->getIds();

            return !empty($countries) ? (string)$countries[0] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Returns a salutation id by its key.
     *
     * @param Context $context
     *
     * @return null|string
     */
    public function getSalutationId(Context $context): ?string
    {
        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('salutationKey', 'not_specified'));

            // Get salutations
            /** @var string[] $salutations */
            $salutations = $this->salutationRepository->searchIds($criteria, $context)->getIds();

            return !empty($salutations) ? (string)$salutations[0] : null;
        } catch (Exception $e) {
            return null;
        }
    }
}
