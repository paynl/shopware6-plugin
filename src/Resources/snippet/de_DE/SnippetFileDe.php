<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Resources\snippet\de_DE;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetFileDe implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'storefront.de-DE';
    }

    public function getPath(): string
    {
        return __DIR__ . '/storefront.de-DE.json';
    }

    public function getIso(): string
    {
        return 'de-DE';
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
