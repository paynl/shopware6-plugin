<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Resources\snippet\ro_RO;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetFileRo implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'storefront.ro-RO';
    }

    public function getPath(): string
    {
        return __DIR__ . '/storefront.ro-RO.json';
    }

    public function getIso(): string
    {
        return 'ro-RO';
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
