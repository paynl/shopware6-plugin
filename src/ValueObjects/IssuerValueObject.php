<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\ValueObjects;

class IssuerValueObject
{
    const ID = 'id';
    const ISSUER_ID = 'issuerId';
    const NAME = 'name';

    private string $id;
    private string $issuerId;
    private string $name;

    public function __construct(string $id, string $issuerId, string $name)
    {
        $this->id = $id;
        $this->issuerId = $issuerId;
        $this->name = $name;
    }

    /**
     * Build an issuer from a getConfig payment method option or from {@see toArray()}, or null when it is not a bank.
     */
    public static function fromArray(array $data): ?self
    {
        $issuerId = trim((string) ($data[self::ISSUER_ID] ?? ''));
        $name = trim((string) ($data[self::NAME] ?? ''));

        if ($issuerId === '' || $name === '') {
            return null;
        }

        return new self((string) ($data[self::ID] ?? ''), $issuerId, $name);
    }

    /**
     * Build the banks from a list of getConfig options or stored banks, skipping everything that is not a bank.
     *
     * @param array<mixed> $items
     * @return self[]
     */
    public static function fromArrays(array $items): array
    {
        $issuers = [];
        foreach ($items as $item) {
            $issuer = is_array($item) || is_object($item) ? self::fromArray((array) $item) : null;
            if ($issuer !== null) {
                $issuers[] = $issuer;
            }
        }

        return $issuers;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getIssuerId(): string
    {
        return $this->issuerId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function toArray(): array
    {
        return [
            self::ID => $this->getId(),
            self::ISSUER_ID => $this->getIssuerId(),
            self::NAME => $this->getName(),
        ];
    }
}
