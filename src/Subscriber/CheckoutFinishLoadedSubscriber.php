<?php

namespace PaynlPayment\Shopware6\Subscriber;

use PaynlPayment\Shopware6\Entity\PaynlTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CheckoutFinishLoadedSubscriber implements EventSubscriberInterface
{
    /**
     * @var PaynlTransactionEntity
     */
    private $paynlTransactionRepository;

    public function __construct(EntityRepositoryInterface $paynlTransactionRepository)
    {
        $this->paynlTransactionRepository = $paynlTransactionRepository;
    }

    public static function getSubscribedEvents()
    {
        return [
            CheckoutFinishPageLoadedEvent::class => 'onCheckoutFinishLoaded',
        ];
    }

    public function onCheckoutFinishLoaded(CheckoutFinishPageLoadedEvent $event): void
    {
        $this->addPaynlTransactionStatus($event);
    }

    private function addPaynlTransactionStatus(CheckoutFinishPageLoadedEvent $event): void
    {
        $order = $event->getPage()->getOrder();

        /** @var PaynlTransactionEntity $paynlTransaction */
        $paynlTransaction = $this->paynlTransactionRepository
            ->search(
                (new Criteria())->addFilter(new EqualsFilter('orderId', $order->getId())),
                Context::createDefaultContext()
            )
            ->first();

        if ($paynlTransaction instanceof PaynlTransactionEntity) {
            $event->getPage()->assign(['PAY_transaction_state_id' => $paynlTransaction->getStateId()]);
        }
    }
}
