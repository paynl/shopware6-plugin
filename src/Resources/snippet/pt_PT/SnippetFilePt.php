<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Resources\snippet\pt_PT;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetFilePt implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'storefront.pt-PT';
    }

    public function getPath(): string
    {
        return __DIR__ . '/storefront.pt-PT.json';
    }

    public function getIso(): string
    {
        return 'pt-PT';
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
