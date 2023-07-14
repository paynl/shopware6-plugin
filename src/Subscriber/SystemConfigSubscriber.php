<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Subscriber;

use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Helper\InstallHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SystemConfigSubscriber implements EventSubscriberInterface
{
    /** @var Config */
    private $config;
    /** @var InstallHelper */
    private $installHelper;

    public function __construct(Config $config, InstallHelper $installHelper)
    {
        $this->config = $config;
        $this->installHelper = $installHelper;
    }

    public static function getSubscribedEvents()
    {
        return [
            SystemConfigChangedEvent::class => 'onSystemConfigChanged',
        ];
    }

    public function onSystemConfigChanged(SystemConfigChangedEvent $event)
    {
        if ($event->getKey() === 'PaynlPaymentShopware6.config.apiToken') {
            $this->installPaymentMethods();
        }
    }

    private function installPaymentMethods()
    {
        $context = Context::createDefaultContext();
        $salesChannelsIds = $this->installHelper->getSalesChannels($context)->getIds();

        foreach ($salesChannelsIds as $salesChannelId) {
            if ($this->config->getSinglePaymentMethodInd($salesChannelId)) {
                $this->installHelper->addSinglePaymentMethod($salesChannelId, $context);

                $paymentMethodId = md5((string)InstallHelper::SINGLE_PAYMENT_METHOD_ID);
                $this->installHelper->setDefaultPaymentMethod($salesChannelId, $context, $paymentMethodId);

                continue;
            }

            $this->installHelper->removeSinglePaymentMethod($salesChannelId, $context);

            $this->installHelper->installPaymentMethods($salesChannelId, $context);
            $this->installHelper->activatePaymentMethods($context);
        }

    }
}
