<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects;

class SettingsSelectOptionValueObject
{
    const ID = 'id';
    const LABEL = 'label';

    private $id;
    private $label;

    public function __construct(string $id, string $label)
    {
        $this->id = $id;
        $this->label = $label;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function toArray(): array
    {
        return [
            self::ID => $this->getId(),
            self::LABEL => $this->getLabel(),
        ];
    }
}
