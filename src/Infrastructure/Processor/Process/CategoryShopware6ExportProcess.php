<?php
/**
 * Copyright © Ergonode Sp. z o.o. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Infrastructure\Processor\Process;

use Ergonode\Category\Domain\Entity\AbstractCategory;
use Ergonode\Channel\Domain\Entity\Export;
use Ergonode\Channel\Domain\Repository\ExportRepositoryInterface;
use Ergonode\Channel\Domain\ValueObject\ExportLineId;
use Ergonode\Core\Domain\ValueObject\Language;
use Ergonode\ExporterShopware6\Domain\Entity\Shopware6Channel;
use Ergonode\ExporterShopware6\Domain\Repository\CategoryRepositoryInterface;
use Ergonode\ExporterShopware6\Domain\Repository\LanguageRepositoryInterface;
use Ergonode\ExporterShopware6\Infrastructure\Builder\CategoryBuilder;
use Ergonode\ExporterShopware6\Infrastructure\Client\Shopware6CategoryClient;
use Ergonode\ExporterShopware6\Infrastructure\Model\Shopware6Category;
use Ergonode\ExporterShopware6\Infrastructure\Model\Shopware6Language;
use Ergonode\SharedKernel\Domain\Aggregate\CategoryId;
use GuzzleHttp\Exception\ClientException;
use Webmozart\Assert\Assert;

class CategoryShopware6ExportProcess
{
    private CategoryRepositoryInterface $shopware6CategoryRepository;

    private Shopware6CategoryClient $categoryClient;

    private CategoryBuilder $builder;

    private LanguageRepositoryInterface  $languageRepository;

    private ExportRepositoryInterface $exportRepository;

    public function __construct(
        CategoryRepositoryInterface $shopware6CategoryRepository,
        Shopware6CategoryClient $categoryClient,
        CategoryBuilder $builder,
        LanguageRepositoryInterface $languageRepository,
        ExportRepositoryInterface $exportRepository
    ) {
        $this->shopware6CategoryRepository = $shopware6CategoryRepository;
        $this->categoryClient = $categoryClient;
        $this->builder = $builder;
        $this->languageRepository = $languageRepository;
        $this->exportRepository = $exportRepository;
    }

    public function process(
        ExportLineId $lineId,
        Export $export,
        Shopware6Channel $channel,
        AbstractCategory $category,
        ?CategoryId $parentId = null
    ): void {
        $shopwareCategory = $this->loadCategory($channel, $category);
        if ($shopwareCategory) {
            $this->updateFullCategory($channel, $export, $shopwareCategory, $category, $parentId);
        } else {
            $shopwareCategory = new Shopware6Category();
            $this->builder->build($channel, $export, $shopwareCategory, $category, $parentId);
            $this->categoryClient->insert($channel, $shopwareCategory, $category);
        }

        $this->exportRepository->processLine($lineId);
    }

    private function updateFullCategory(
        Shopware6Channel $channel,
        Export $export,
        Shopware6Category $shopwareCategory,
        AbstractCategory $category,
        ?CategoryId $parentId = null,
        ?Language $language = null,
        ?Shopware6Language $shopwareLanguage = null
    ): void {
        $requireUpdate = false;
        $this->builder->build($channel, $export, $shopwareCategory, $category, $parentId, $language);
        if ($shopwareCategory->isModified()) {
            $requireUpdate = true;
        }

        foreach ($channel->getLanguages() as $channelLanguage) {
            if ($this->languageRepository->exists($channel->getId(), $channelLanguage->getCode())) {
                $shopwareLanguage = $this->languageRepository->load($channel->getId(), $channelLanguage->getCode());
                Assert::notNull(
                    $shopwareLanguage,
                    sprintf('Expected a value other than null for product lang  %s', $channelLanguage->getCode())
                );

                $translatedCategory = $shopwareCategory->getTranslated($shopwareLanguage);
                $translatedCategory = $this->builder->build($channel, $export, $translatedCategory, $category, $parentId,  $channelLanguage);
                $shopwareCategory->updateTranslated($translatedCategory, $shopwareLanguage);

                if ($translatedCategory->isModified()) {
                    $requireUpdate = true;
                }
            }
        }

        if ($requireUpdate) {
            $this->categoryClient->update($channel, $shopwareCategory, $shopwareLanguage);
        }
    }

    private function loadCategory(
        Shopware6Channel $channel,
        AbstractCategory $category
    ): ?Shopware6Category {
        $shopwareId = $this->shopware6CategoryRepository->load($channel->getId(), $category->getId());
        if ($shopwareId) {
            try {
                return $this->categoryClient->get($channel, $shopwareId);
            } catch (ClientException $exception) {
            }
        }

        return null;
    }
}
