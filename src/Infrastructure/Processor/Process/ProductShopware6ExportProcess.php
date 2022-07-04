<?php

declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Infrastructure\Processor\Process;

use Ergonode\Channel\Domain\Entity\Export;
use Ergonode\Channel\Domain\Repository\ExportRepositoryInterface;
use Ergonode\Channel\Domain\ValueObject\ExportLineId;
use Ergonode\Core\Domain\ValueObject\Language;
use Ergonode\ExporterShopware6\Domain\Entity\Shopware6Channel;
use Ergonode\ExporterShopware6\Domain\Repository\LanguageRepositoryInterface;
use Ergonode\ExporterShopware6\Domain\Repository\ProductRepositoryInterface;
use Ergonode\ExporterShopware6\Infrastructure\Builder\ProductBuilder;
use Ergonode\ExporterShopware6\Infrastructure\Client\Shopware6ProductClient;
use Ergonode\ExporterShopware6\Infrastructure\Exception\Shopware6ExporterException;
use Ergonode\ExporterShopware6\Infrastructure\Model\Shopware6Language;
use Ergonode\ExporterShopware6\Infrastructure\Model\Shopware6Product;
use Ergonode\Product\Domain\Entity\AbstractProduct;
use Webmozart\Assert\Assert;

class ProductShopware6ExportProcess
{
    protected ProductRepositoryInterface $shopwareProductRepository;

    private ProductBuilder $builder;

    private Shopware6ProductClient $productClient;

    private LanguageRepositoryInterface $languageRepository;

    private ExportRepositoryInterface $exportRepository;

    public function __construct(
        ProductBuilder $builder,
        Shopware6ProductClient $productClient,
        LanguageRepositoryInterface $languageRepository,
        ExportRepositoryInterface $exportRepository,
        ProductRepositoryInterface $shopwareProductRepository
    ) {
        $this->builder = $builder;
        $this->productClient = $productClient;
        $this->languageRepository = $languageRepository;
        $this->exportRepository = $exportRepository;
        $this->shopwareProductRepository = $shopwareProductRepository;
    }

    /**
     * @throws \Exception
     */
    public function process(
        ExportLineId $lineId,
        Export $export,
        Shopware6Channel $channel,
        AbstractProduct $product
    ): void {
        $shopwareProduct = $this->productClient->find($channel, $product);

        try {
            if ($shopwareProduct) {
                $this->updateFullProduct($channel, $export, $shopwareProduct, $product);
            } else {
                $shopwareProduct = new Shopware6Product();
                $this->builder->build($channel, $export, $shopwareProduct, $product);
                $this->productClient->insert($channel, $shopwareProduct, $product->getId());
                $shopwareId = $this->shopwareProductRepository->load($channel->getId(), $product->getId());
                if (!$shopwareId) {
                    throw new Shopware6ExporterException(
                        sprintf("Failed inserting product %s", $product->getSku()->getValue())
                    );
                }
                $shopwareProduct->setId($shopwareId);

                $this->updateFullProduct($channel, $export, $shopwareProduct, $product);
            }
        } catch (Shopware6ExporterException $exception) {
            $this->exportRepository->addError($export->getId(), $exception->getMessage(), $exception->getParameters());
        }
        $this->exportRepository->processLine($lineId);
    }

    private function updateFullProduct(
        Shopware6Channel $channel,
        Export $export,
        Shopware6Product $shopwareProduct,
        AbstractProduct $product
    ): void {
        $requireUpdate = false;

        $shopwareLanguage = $this->languageRepository->load($channel->getId(), $channel->getDefaultLanguage()->getCode());
        Assert::notNull(
            $shopwareLanguage,
            sprintf('Expected a value other than null for product lang  %s', $channel->getDefaultLanguage()->getCode())
        );

        $shopwareProduct = $this->builder->build($channel, $export, $shopwareProduct, $product);
        $shopwareProduct->updateTranslated($shopwareProduct, $shopwareLanguage);
        if ($shopwareProduct->isModified() || $shopwareProduct->hasItemToRemoved()) {
            $requireUpdate = true;
        }

        foreach ($channel->getLanguages() as $channelLanguage) {
            if ($this->languageRepository->exists($channel->getId(), $channelLanguage->getCode())) {
                $shopwareLanguage = $this->languageRepository->load($channel->getId(), $channelLanguage->getCode());
                Assert::notNull(
                    $shopwareLanguage,
                    sprintf('Expected a value other than null for product lang  %s', $channelLanguage->getCode())
                );

                $translatedProduct = $shopwareProduct->getTranslated($shopwareLanguage);
                $translatedProduct = $this->builder->build($channel, $export, $translatedProduct, $product, $channelLanguage);
                $shopwareProduct->updateTranslated($translatedProduct, $shopwareLanguage);

                if ($translatedProduct->isModified() || $translatedProduct->hasItemToRemoved()) {
                    $requireUpdate = true;
                }
            }
        }
        if ($requireUpdate) {
            $this->productClient->update($channel, $shopwareProduct, $shopwareLanguage);
        }
    }
}
