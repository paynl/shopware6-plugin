<?php declare(strict_types=1);

namespace Swag\PaynlPayment\Helper;

use Paynl\Paymentmethods;
use Paynl\Config;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class PaynlSDKHelper
{
    /**
     * @var SystemConfigService
     */
    public $systemConfigService;

    /**
     * SettingsService constructor.
     * @param SystemConfigService $systemConfigService
     */
    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
        $tokenCode = $this->getSetting('tokenCode');
        $apiToken = $this->getSetting('apiToken');
        $serviceId = $this->getSetting('serviceId');
        Config::setTokenCode($tokenCode);
        Config::setApiToken($apiToken);
        Config::setServiceId($serviceId);
    }

    /**
     * @param string $setting
     * @return mixed|null
     */
    public function getSetting(string $setting)
    {
        return $this->systemConfigService->get('PaynlPayment.config.' . $setting);
    }

    public function getPaymentMethods() : array
    {
        return Paymentmethods::getList();
    }
}
