<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Resources\snippet\pl_PL;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetFilePl implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'storefront.pl-PL';
    }

    public function getPath(): string
    {
        return __DIR__ . '/storefront.pl-PL.json';
    }

    public function getIso(): string
    {
        return 'pl-PL';
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
