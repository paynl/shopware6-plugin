<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Resources\snippet\en_GB;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetFileEn implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'storefront.en-GB';
    }

    public function getPath(): string
    {
        return __DIR__ . '/storefront.en-GB.json';
    }

    public function getIso(): string
    {
        return 'en-GB';
    }

    public function getAuthor(): string
    {
        return 'Pay. payments';
    }

    public function isBase(): bool
    {
        return false;
    }
}
