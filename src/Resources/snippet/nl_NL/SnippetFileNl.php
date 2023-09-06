<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Resources\snippet\nl_NL;

use Shopware\Core\System\Snippet\Files\AbstractSnippetFile;

class SnippetFileNl extends AbstractSnippetFile
{
    public function getName(): string
    {
        return 'storefront.nl-NL';
    }

    public function getPath(): string
    {
        return __DIR__ . '/storefront.nl-NL.json';
    }

    public function getIso(): string
    {
        return 'nl-NL';
    }

    public function getAuthor(): string
    {
        return 'Pay. payments';
    }

    public function isBase(): bool
    {
        return false;
    }

    public function getTechnicalName(): string
    {
        return $this->getName();
    }
}
