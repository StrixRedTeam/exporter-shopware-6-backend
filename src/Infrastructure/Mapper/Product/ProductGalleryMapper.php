<?php

declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Infrastructure\Mapper\Product;

use Ergonode\Attribute\Domain\Repository\AttributeRepositoryInterface;
use Ergonode\Channel\Domain\Entity\Export;
use Ergonode\Core\Domain\ValueObject\Language;
use Ergonode\ExporterShopware6\Domain\Entity\Shopware6Channel;
use Ergonode\ExporterShopware6\Infrastructure\Client\Shopware6ProductMediaClient;
use Ergonode\ExporterShopware6\Infrastructure\Mapper\ProductMapperInterface;
use Ergonode\ExporterShopware6\Infrastructure\Model\Product\Shopware6ProductMedia;
use Ergonode\ExporterShopware6\Infrastructure\Model\Shopware6Product;
use Ergonode\Multimedia\Domain\Repository\MultimediaRepositoryInterface;
use Ergonode\Product\Domain\Entity\AbstractProduct;
use Ergonode\Product\Infrastructure\Calculator\TranslationInheritanceCalculator;
use Ergonode\SharedKernel\Domain\Aggregate\MultimediaId;
use Webmozart\Assert\Assert;

class ProductGalleryMapper implements ProductMapperInterface
{
    private AttributeRepositoryInterface $repository;

    private TranslationInheritanceCalculator $calculator;

    private MultimediaRepositoryInterface $multimediaRepository;

    private Shopware6ProductMediaClient $mediaClient;

    public function __construct(
        AttributeRepositoryInterface $repository,
        TranslationInheritanceCalculator $calculator,
        MultimediaRepositoryInterface $multimediaRepository,
        Shopware6ProductMediaClient $mediaClient
    ) {
        $this->repository = $repository;
        $this->calculator = $calculator;
        $this->multimediaRepository = $multimediaRepository;
        $this->mediaClient = $mediaClient;
    }

    /**
     * {@inheritDoc}
     */
    public function map(
        Shopware6Channel $channel,
        Export $export,
        Shopware6Product $shopware6Product,
        AbstractProduct $product,
        ?Language $language = null
    ): Shopware6Product {
        if (null === $channel->getAttributeProductGallery()) {
            return $shopware6Product;
        }
        /**
         * Don't map images for non default language. Handlers don't flush data in real time but transactional. Other languages don't know about default image being already saved.
         */
        if (!is_null($language)) {
            return $shopware6Product;
        }
        $attribute = $this->repository->load($channel->getAttributeProductGallery());

        Assert::notNull($attribute,sprintf('Expected a value other than null for gallery attribute %s', $channel->getAttributeProductGallery()->getValue()));

        if (false === $product->hasAttribute($attribute->getCode())) {
            return $shopware6Product;
        }

        $value = $product->getAttribute($attribute->getCode());
        $calculateValue = $this->calculator->calculate($attribute->getScope(), $value, $language ?: $channel->getDefaultLanguage());
        if ($calculateValue) {
            if (!is_array($calculateValue)) {
                $calculateValue = [$calculateValue];
            }
            $position = 0;
            foreach ($calculateValue as $galleryValue) {
                $multimediaId = new MultimediaId($galleryValue);
                $this->getShopware6MultimediaId($multimediaId, $shopware6Product, $channel, $position++);
            }
        }

        return $shopware6Product;
    }

    private function getShopware6MultimediaId(
        MultimediaId $multimediaId,
        Shopware6Product $shopware6Product,
        Shopware6Channel $channel,
        int $position
    ): Shopware6Product {
        $media = new Shopware6ProductMedia(null, $multimediaId->getValue(), $position);
        if ($shopware6Product->hasMedia($media)) {
            $shopware6Product->unsetMediaRemove($media);
            return $shopware6Product;
        }

        $multimedia = $this->multimediaRepository->load($multimediaId);
        if ($multimedia) {
            $shopwareId = $this->mediaClient->findOrCreateMedia($channel, $multimedia, $shopware6Product);
            if ($shopwareId) {
                $shopware6Product->addMedia(new Shopware6ProductMedia(null, $shopwareId, $position));
            }
        }

        return $shopware6Product;
    }
}
