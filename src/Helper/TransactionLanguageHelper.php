<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Helper;

use PaynlPayment\Shopware6\Components\Config;
use PaynlPayment\Shopware6\Enums\LanguageEnum;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\RequestStack;

class TransactionLanguageHelper
{
    const DEFAULT_LANGUAGE = LanguageEnum::NL;
    const HTTP_ACCEPT_LANGUAGE_Q_VALUE_PATTERN = "/(.*);q=([0-1]{0,1}.[0-9]{0,4})/i";

    /**
     * @var Config
     */
    private $config;

    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepository;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(
        Config $config,
        EntityRepositoryInterface $languageRepository,
        RequestStack $requestStack
    ) {
        $this->config = $config;
        $this->languageRepository = $languageRepository;
        $this->requestStack = $requestStack;
    }

    public function getLanguageForOrder(OrderEntity $order): string
    {
        $languageSetting = $this->config->getPaymentScreenLanguage($order->getSalesChannelId());

        if ($languageSetting == 'auto') {
            return $this->getBrowserLanguage();
        }

        if ($languageSetting == 'cart') {
            return $this->getLanguageById($order->getLanguageId());
        }

        return $languageSetting;
    }

    private function getBrowserLanguage(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (is_null($request)) {
            return self::DEFAULT_LANGUAGE;
        }

        return $this->parseDefaultLanguage((string)$request->server->get('HTTP_ACCEPT_LANGUAGE'));
    }

    private function parseDefaultLanguage(string $httpAccept): string
    {
        $defLang = self::DEFAULT_LANGUAGE;

        if (strlen($httpAccept) > 1) {
            $lang = [];
            # Split possible languages into array
            $x = explode(",", $httpAccept);
            foreach ($x as $val) {
                #check for q-value and create associative array. No q-value means 1 by rule
                if (preg_match(self::HTTP_ACCEPT_LANGUAGE_Q_VALUE_PATTERN, $val, $matches)) {
                    $lang[$matches[1]] = (float)$matches[2] . '';

                    continue;
                }

                $lang[$val] = 1.0;
            }

            $arrLanguages = $this->getLanguages();
            $arrAvailableLanguages = [];
            foreach ($arrLanguages as $language) {
                if ($language['id'] != 'auto') {
                    $arrAvailableLanguages[] = $language['id'];
                }
            }

            #return default language (highest q-value)
            $qVal = 0.0;
            foreach ($lang as $key => $value) {
                $languageCode = strtolower(substr($key, 0, 2));

                if (!in_array($languageCode, $arrAvailableLanguages)) {
                    continue;
                }

                if ($value > $qVal) {
                    $qVal = (float)$value;
                    $defLang = $key;
                }
            }
        }

        return strtolower(substr($defLang, 0, 2));
    }

    public function getLanguages(): array
    {
        return [
            [
                'id' => LanguageEnum::NL,
                'label' => 'Dutch'
            ],
            [
                'id' => LanguageEnum::EN,
                'label' => 'English'
            ],
            [
                'id' => LanguageEnum::ES,
                'label' => 'Spanish'
            ],
            [
                'id' => LanguageEnum::IT,
                'label' => 'Italian'
            ],
            [
                'id' => LanguageEnum::FR,
                'label' => 'French'
            ],
            [
                'id' => LanguageEnum::DE,
                'label' => 'German'
            ],
            [
                'id' => 'cart',
                'label' => 'Shopware language'
            ],
            [
                'id' => 'auto',
                'label' => 'Automatic (Browser language)'
            ],
        ];
    }

    private function getLanguageById(string $languageId): string
    {
        if (empty($languageId)) {
            return self::DEFAULT_LANGUAGE;
        }

        $criteria  = new Criteria([$languageId]);
        $criteria->addAssociation('locale');

        /** @var null|LanguageEntity $language */
        $language = $this->languageRepository->search($criteria, Context::createDefaultContext())->first();

        if (is_null($language) || is_null($language->getLocale())) {
            return self::DEFAULT_LANGUAGE;
        }

        return substr($language->getLocale()->getCode(), 0, 2);
    }

}
