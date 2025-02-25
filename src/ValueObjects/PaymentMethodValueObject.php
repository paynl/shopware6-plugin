<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects;

use PayNL\Sdk\Model\Method;
use PaynlPayment\Shopware6\Enums\PayLaterPaymentMethodsEnum;

class PaymentMethodValueObject
{
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

    public function getBanks(): array
    {
        return $this->originalMethod->getId() === Method::IDEAL ? $this->originalMethod->getOptions() : [];
    }

    public function isPayLater(): bool
    {
        return in_array($this->originalMethod->getId(), PayLaterPaymentMethodsEnum::PAY_LATER_PAYMENT_METHODS);
    }
}
