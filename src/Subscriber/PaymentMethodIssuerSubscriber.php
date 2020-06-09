<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent;

class PaymentMethodIssuerSubscriber implements EventSubscriberInterface
{
    /**
     * @var Session
     */
    private $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent' => 'onPostDispatchCheckout'
        ];
    }


    public function onPostDispatchCheckout(SalesChannelContextSwitchEvent $event)
    {
        $this->session->set('paynlIssuer',  $event->getRequestDataBag()->get('paynlIssuer'));
    }
}
