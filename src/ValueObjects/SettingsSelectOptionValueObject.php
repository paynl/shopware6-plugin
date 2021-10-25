<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects;

class SettingsSelectOptionValueObject
{
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
            'id' => $this->getId(),
            'label' => $this->getLabel(),
        ];
    }
}
