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

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FooterPageletLoadedEvent::class => 'addCustomData',
        ];
    }

    public function addCustomData(FooterPageletLoadedEvent $event): void
    {
        $configs = $this->systemConfigService->all();
        $customData = new CustomPageDataValueObject($configs);

        $event->getPagelet()->addExtension('PAY_custom_data', $customData);
    }
}
