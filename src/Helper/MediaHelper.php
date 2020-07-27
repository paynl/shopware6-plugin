<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Helper;

use PaynlPayment\Shopware6\ValueObjects\PaymentMethodValueObject;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MediaHelper
{
    const MEDIA_NAME_TEMPLATE = 'paynlpayment_%s';
    const FILE_PATH_TEMPLATE = __DIR__ . '/../Resources/public/logos/%s.png';

    /** @var EntityRepositoryInterface */
    private $mediaRepository;

    /** @var FileSaver */
    private $fileSaver;

    public function __construct(ContainerInterface $container)
    {
        $this->mediaRepository = $container->get('media.repository');
        $this->fileSaver = $container->get(FileSaver::class);
    }

    /**
     * @param string $paymentMethodName
     * @param Context $context
     * @return string|null
     */
    public function getMediaId(string $paymentMethodName, Context $context): ?string
    {
        $criteria = (new Criteria())->addFilter(
            new EqualsFilter(
                'fileName',
                $this->getMediaName($paymentMethodName)
            )
        );

        /** @var MediaEntity $media */
        $media = $this->mediaRepository->search($criteria, $context)->first();

        if (empty($media)) {
            return null;
        }

        return $media->getId();
    }

    public function addImageToMedia(PaymentMethodValueObject $paymentMethodValueObject, Context $context): void
    {
        if ($this->isAlreadyExist($paymentMethodValueObject->getName(), $context)) {
           $this->deleteMedia($this->getMediaIds($paymentMethodValueObject->getName(), $context), $context);
        }

        $paymentMethodBrandId = $paymentMethodValueObject->getBrandId();
        $filePath = sprintf(self::FILE_PATH_TEMPLATE, $paymentMethodBrandId);
        if (!file_exists($filePath)) {
            return;
        }

        $mediaFile = new MediaFile(
            $filePath,
            mime_content_type($filePath),
            pathinfo($filePath, PATHINFO_EXTENSION),
            filesize($filePath)
        );

        $mediaId = Uuid::randomHex();
        $mediaData = ['id' => $mediaId];
        $this->mediaRepository->create([$mediaData], $context);

        $this->fileSaver->persistFileToMedia(
            $mediaFile,
            $this->getMediaName($paymentMethodValueObject->getName()),
            $mediaId,
            $context
        );
    }

    private function isAlreadyExist(string $paymentMethodName, Context $context) : bool
    {
        $criteria = (new Criteria())->addFilter(
            new EqualsFilter('fileName', $this->getMediaName($paymentMethodName))
        );

        $media = $this->mediaRepository->search($criteria, $context)->first();

        return !empty($media);
    }

    public function getMediaIds(string $paymentMethodsName, Context $context): array
    {
        $criteria = (new Criteria())->addFilter(
            new EqualsFilter('fileName', $this->getMediaName($paymentMethodsName))
        );
        $ids = $this->mediaRepository->searchIds($criteria, $context)->getIds();
        $ids = array_map(static function ($id) {
            return ['id' => $id];
        }, $ids);

        return $ids;
    }

    public function deleteMedia(array $ids, Context $context): void
    {
        $this->mediaRepository->delete($ids, $context);
    }

    private function getMediaName(string $paymentMethodName): string
    {
        $paymentMethodName = strtolower(trim(preg_replace('/[\W]/', '_', $paymentMethodName), '_'));

        return sprintf(self::MEDIA_NAME_TEMPLATE, $paymentMethodName);
    }
}
