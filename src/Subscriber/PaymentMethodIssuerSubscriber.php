<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Subscriber;

use Shopware\Core\Checkout\Customer\Event\CustomerChangedPaymentMethodEvent;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
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
            'Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent' => 'onCheckoutPaymentMethodChange',
            'Shopware\Core\Checkout\Customer\Event\CustomerChangedPaymentMethodEvent' => 'onPaymentMethodChanged'
        ];
    }

    /**
     * @param SalesChannelContextSwitchEvent $event
     */
    public function onCheckoutPaymentMethodChange(SalesChannelContextSwitchEvent $event)
    {
        $this->setPaynlIssuer((int)$event->getRequestDataBag()->get('paynlIssuer'));
    }

    /**
     * @param CustomerChangedPaymentMethodEvent $event
     */
    public function onPaymentMethodChanged(CustomerChangedPaymentMethodEvent $event)
    {
        $this->setPaynlIssuer((int)$event->getRequestDataBag()->get('paynlIssuer'));
    }

    /**
     * @param int $paynlIssuer
     */
    private function setPaynlIssuer(int $paynlIssuer)
    {
        if (!empty($paynlIssuer)) {
            $this->session->set('paynlIssuer', $paynlIssuer);
        }
    }
}
