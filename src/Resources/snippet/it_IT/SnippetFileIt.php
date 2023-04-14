<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Resources\snippet\it_IT;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetFileIt implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'storefront.it-IT';
    }

    public function getPath(): string
    {
        return __DIR__ . '/storefront.it-IT.json';
    }

    public function getIso(): string
    {
        return 'it-IT';
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
