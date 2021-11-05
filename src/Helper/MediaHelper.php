<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Helper;

use PaynlPayment\Shopware6\ValueObjects\PaymentMethodValueObject;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class MediaHelper
{
    const MEDIA_NAME_TEMPLATE = 'paynlpayment_%s';
    const MEDIA_NAME_PREFIX = 'paynlpayment';
    const FILE_PATH_TEMPLATE = __DIR__ . '/../Resources/public/logos/%s.png';

    /** @var EntityRepositoryInterface */
    private $mediaRepository;

    /** @var FileSaver */
    private $fileSaver;

    public function __construct(
        FileSaver $fileSaver,
        EntityRepositoryInterface $mediaRepository
    ) {
        $this->fileSaver = $fileSaver;
        $this->mediaRepository = $mediaRepository;
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
            return;
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

    public function removeOldMedia(Context $context, array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $criteria = new Criteria($ids);

        $mediaIds = $this->mediaRepository->searchIds($criteria, $context)->getIds();
        $mediaIds = array_map(static function ($id) {
            return ['id' => $id];
        }, $mediaIds);

        if (empty($mediaIds)) {
            return;
        }

        $this->mediaRepository->delete($mediaIds, $context);
    }

    public function removeOldMediaAll(Context $context): void
    {
        $criteria = (new Criteria())->addFilter(
            new ContainsFilter('fileName', self::MEDIA_NAME_PREFIX)
        );
        $mediaIds = $this->mediaRepository->searchIds($criteria, $context)->getIds();
        $mediaIds = array_map(static function ($id) {
            return ['id' => $id];
        }, $mediaIds);

        if (empty($mediaIds)) {
            return;
        }

        $this->mediaRepository->delete($mediaIds, $context);
    }

    private function getMediaName(string $paymentMethodName): string
    {
        $paymentMethodName = strtolower(trim(preg_replace('/[\W]/', '_', $paymentMethodName), '_'));

        return sprintf(self::MEDIA_NAME_TEMPLATE, $paymentMethodName);
    }
}
