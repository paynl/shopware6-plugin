<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Service;

use PaynlPayment\Shopware6\ValueObjects\CustomPageDataValueObject;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Pagelet\Footer\FooterPageletLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AddDataToPage implements EventSubscriberInterface
{
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var string
     */
    private $shopwareVersion;

    public function __construct(SystemConfigService $systemConfigService, string $shopwareVersion)
    {
        $this->systemConfigService = $systemConfigService;
        $this->shopwareVersion = $shopwareVersion;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FooterPageletLoadedEvent::class => 'addCustomData',
        ];
    }

    public function addCustomData(FooterPageletLoadedEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelContext()->getSalesChannel()->getId();
        $configs = $this->systemConfigService->all($salesChannelId);
        $customData = new CustomPageDataValueObject($configs, $this->shopwareVersion);

        $event->getPagelet()->addExtension('PAY_custom_data', $customData);
    }
}
