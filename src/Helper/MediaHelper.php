<?php declare(strict_types=1);

namespace PaynlPayment\Shopware6\Helper;

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
    const PAYNL_PAYMENT_MEDIA_TEMPLATE = 'paynlpayment_%s_%s';

    /** @var EntityRepositoryInterface */
    private $mediaRepository;
    /** @var FileSaver */
    private $fileSaver;

    private $imageRoute = __DIR__ . '/../Resources/public/logos/%s.png';

    public function __construct(ContainerInterface $container)
    {
        $this->mediaRepository = $container->get('media.repository');
        $this->fileSaver = $container->get(FileSaver::class);
    }

    /**
     * @param array $paymentMethod
     * @param Context $context
     */
    public function addMedia(array $paymentMethod, Context $context): void
    {
        if ($this->hasMediaAlreadyInstalled($paymentMethod, $context)) {
            return;
        }

        $mediaName = sprintf($this->imageRoute, $paymentMethod['id']);
        if (!file_exists($mediaName)) {
            return;
        }

        $mediaFile = $this->createMediaFile($mediaName);
        $mediaId = Uuid::randomHex();
        $mediaData = ['id' => $mediaId];

        $this->mediaRepository->create(
            [$mediaData],
            $context
        );

        $this->fileSaver->persistFileToMedia(
            $mediaFile,
            $this->getMediaName($paymentMethod),
            $mediaId,
            $context
        );
    }

    /**
     * @param string $filePath
     * @return MediaFile
     */
    private function createMediaFile(string $filePath): MediaFile
    {
        return new MediaFile(
            $filePath,
            mime_content_type($filePath),
            pathinfo($filePath, PATHINFO_EXTENSION),
            filesize($filePath)
        );
    }

    /**
     * @param array $paymentMethod
     * @param Context $context
     * @return bool
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    private function hasMediaAlreadyInstalled(array $paymentMethod, Context $context) : bool
    {
        $criteria = (new Criteria())->addFilter(
            new EqualsFilter(
                'fileName',
                $this->getMediaName($paymentMethod)
            )
        );

        /** @var MediaEntity $media */
        $media = $this->mediaRepository->search($criteria, $context)->first();

        return $media ? true : false;
    }

    /**
     * @param array $paymentMethod
     * @return string
     */
    private function getMediaName(array $paymentMethod): string
    {
        $paymentMethodTechnicalName = $this->getPaymentMethodTechnicalName($paymentMethod['name']);

        return sprintf(self::PAYNL_PAYMENT_MEDIA_TEMPLATE, $paymentMethodTechnicalName, $paymentMethod['id']);
    }

    /**
     * @param string $name
     * @return string
     */
    private function getPaymentMethodTechnicalName(string $name): string
    {
        $technicalName = trim(preg_replace('/[\W]/', '_', $name), '_');
        $technicalName = preg_replace('/(\_+)/', '_', $technicalName);

        return strtolower($technicalName);
    }

    /**
     * @param array $paymentMethod
     * @param Context $context
     * @return string|null
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    public function getMediaId(array $paymentMethod, Context $context): ?string
    {
        $criteria = (new Criteria())->addFilter(
            new EqualsFilter(
                'fileName',
                $this->getMediaName($paymentMethod)
            )
        );

        /** @var MediaEntity $media */
        $media = $this->mediaRepository->search($criteria, $context)->first();

        if (!$media) {
            return null;
        }

        return $media->getId();
    }
}
