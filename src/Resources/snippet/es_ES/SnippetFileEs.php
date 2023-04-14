<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Resources\snippet\es_ES;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetFileEs implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'storefront.es-ES';
    }

    public function getPath(): string
    {
        return __DIR__ . '/storefront.es-ES.json';
    }

    public function getIso(): string
    {
        return 'es-ES';
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
