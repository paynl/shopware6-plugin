<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects;

use PayNL\Sdk\Model\Method;
use PaynlPayment\Shopware6\Enums\PayLaterPaymentMethodsEnum;
use PaynlPayment\Shopware6\Enums\PaynlPaymentMethodsIdsEnum;

class PaymentMethodValueObject
{
    public const TECHNICAL_NAME_PREFIX = 'paynl_';

    private Method $originalMethod;
    private string $hashedId;

    public function __construct(Method $paymentMethod)
    {
        $this->originalMethod = $paymentMethod;
        $this->hashedId = md5((string) $paymentMethod->getId());
    }

    public function getOriginalMethod(): Method
    {
        return $this->originalMethod;
    }

    public function getHashedId(): string
    {
        return $this->hashedId;
    }

    public function getBrandId(): string
    {
        return pathinfo($this->originalMethod->getImage(), PATHINFO_FILENAME);
    }

    public function getTechnicalName(): string
    {
        return self::TECHNICAL_NAME_PREFIX
            . $this->originalMethod->getId()
            . '_'
            . $this->sanitizeName($this->originalMethod->getName());
    }

    /**
     * @return IssuerValueObject[]
     */
    public function getBanks(): array
    {
        $method = $this->originalMethod;

        if ($method->getId() !== PaynlPaymentMethodsIdsEnum::METJEBANK_PAYMENT || !$method->hasOptions()) {
            return [];
        }

        $banks = [];
        foreach ($method->getOptions() as $option) {
            $bank = IssuerValueObject::fromArray((array) $option);
            if ($bank !== null) {
                $banks[] = $bank;
            }
        }

        return $banks;
    }

    public function isPayLater(): bool
    {
        return in_array($this->originalMethod->getId(), PayLaterPaymentMethodsEnum::PAY_LATER_PAYMENT_METHODS);
    }

    private function sanitizeName(string $name): string
    {
        $name = strtolower($name);

        //Replace any non-alphanumeric character with an underscore
        $name = preg_replace('/[^a-z0-9]+/', '_', $name);//NOSONAR

        return trim($name, '_');
    }
}