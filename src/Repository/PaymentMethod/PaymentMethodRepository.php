<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Repository\PaymentMethod;

use PaynlPayment\Shopware6\Enums\PaynlPaymentMethodsIdsEnum;
use PaynlPayment\Shopware6\PaymentHandler\PaynlPaymentHandler;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Exception;

class PaymentMethodRepository implements PaymentMethodRepositoryInterface
{
    /**
     * @var EntityRepository|EntityRepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @param EntityRepository|EntityRepositoryInterface $paymentMethodRepository
     */
    public function __construct($paymentMethodRepository)
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->paymentMethodRepository->upsert($data, $context);
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function create(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->paymentMethodRepository->create($data, $context);
    }


    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return EntitySearchResult
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->paymentMethodRepository->search($criteria, $context);
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->paymentMethodRepository->update($data, $context);
    }

    public function getPaymentMethodById(string $paymentMethodId, Context $context): PaymentMethodEntity
    {
        $criteria = new Criteria([$paymentMethodId]);

        /** @var PaymentMethodEntity|null $paymentMethod */
        $paymentMethod = $this->paymentMethodRepository->search($criteria, $context)->first();

        if ($paymentMethod === null) {
            throw new EntityNotFoundException(PaymentMethodDefinition::ENTITY_NAME, $paymentMethodId);
        }

        return $paymentMethod;
    }

    /**
     * @param Context $context
     * @throws Exception
     * @return string
     */
    public function getActiveIdealID(Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('handlerIdentifier', PaynlPaymentHandler::class));
        $criteria->addFilter(new EqualsFilter('active', true));

        $paymentMethods = $this->paymentMethodRepository->search($criteria, $context)->getElements();

        foreach ($paymentMethods as $paymentMethod) {
            $customFields = $paymentMethod->getTranslation('customFields');
            if (!isset($customFields['paynlId']) || empty($customFields['paynlId'])) {
                continue;
            }

            if ($customFields['paynlId'] === PaynlPaymentMethodsIdsEnum::IDEAL_PAYMENT) {
                return (string) $paymentMethod->get('id');
            }
        }

        throw new Exception('Payment Method IDEAL Express not found in system');
    }

    /**
     * @param Context $context
     * @throws Exception
     * @return string
     */
    public function getActivePayPalID(Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('handlerIdentifier', PaynlPaymentHandler::class));
        $criteria->addFilter(new EqualsFilter('active', true));

        $paymentMethods = $this->paymentMethodRepository->search($criteria, $context)->getElements();

        foreach ($paymentMethods as $paymentMethod) {
            $customFields = $paymentMethod->getTranslation('customFields');
            if (!isset($customFields['paynlId']) || empty($customFields['paynlId'])) {
                continue;
            }

            if ($customFields['paynlId'] === PaynlPaymentMethodsIdsEnum::PAYPAL_PAYMENT) {
                return (string) $paymentMethod->get('id');
            }
        }

        throw new Exception('Payment Method PayPal Express not found in system');
    }
}
