<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Helper;

use PaynlPayment\Shopware6\Components\Config;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\Framework\Context;

class TransactionLanguageHelper
{
    const DEFAULT_LANGUAGE = 'en';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepository;

    public function __construct(Config $config, EntityRepositoryInterface $languageRepository)
    {
        $this->config = $config;
        $this->languageRepository = $languageRepository;
    }

    public function getLanguageForOrder(OrderEntity $order)
    {
        $languageSetting = $this->config->getPaymentScreenLanguage();

        if ($languageSetting == 'auto') {
            return $this->getBrowserLanguage();
        } elseif ($languageSetting == 'cart') {
            return $this->getLanguageById($order->getLanguageId());
        } else {
            return $languageSetting;
        }
    }

    private function getBrowserLanguage()
    {
        if (isset($_SERVER["HTTP_ACCEPT_LANGUAGE"])) {
            return $this->parseDefaultLanguage($_SERVER["HTTP_ACCEPT_LANGUAGE"]);
        } else {
            return $this->parseDefaultLanguage(null);
        }
    }

    private function parseDefaultLanguage($http_accept, $deflang = 'nl')
    {
        if (isset($http_accept) && strlen($http_accept) > 1) {
            $lang = array();
            # Split possible languages into array
            $x = explode(",", $http_accept);
            foreach ($x as $val) {
                #check for q-value and create associative array. No q-value means 1 by rule
                if (preg_match("/(.*);q=([0-1]{0,1}.[0-9]{0,4})/i", $val,
                    $matches)) {
                    $lang[$matches[1]] = (float)$matches[2] . '';
                } else {
                    $lang[$val] = 1.0;
                }
            }

            $arrLanguages = $this->getLanguages();
            $arrAvailableLanguages = array();
            foreach ($arrLanguages as $language) {
                if ($language['id'] != 'auto') {
                    $arrAvailableLanguages[] = $language['id'];
                }
            }

            #return default language (highest q-value)
            $qval = 0.0;
            foreach ($lang as $key => $value) {
                $languagecode = strtolower(substr($key, 0, 2));

                if (in_array($languagecode, $arrAvailableLanguages)) {
                    if ($value > $qval) {
                        $qval = (float)$value;
                        $deflang = $key;
                    }
                }
            }
        }

        return strtolower(substr($deflang, 0, 2));
    }

    public function getLanguages()
    {
        return [
            [
                'id' => 'nl',
                'label' => 'Dutch'
            ],
            [
                'id' => 'en',
                'label' => 'English'
            ],
            [
                'id' => 'es',
                'label' => 'Spanish'
            ],
            [
                'id' => 'it',
                'label' => 'Italian'
            ],
            [
                'id' => 'fr',
                'label' => 'French'
            ],
            [
                'id' => 'de',
                'label' => 'German'
            ]
        ];
    }

    private function getLanguageById(string $languageId)
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
