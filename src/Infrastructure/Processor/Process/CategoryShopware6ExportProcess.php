<?php
/**
 * Copyright © Ergonode Sp. z o.o. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Infrastructure\Processor\Process;

use Ergonode\Category\Domain\Entity\AbstractCategory;
use Ergonode\Category\Domain\Entity\CategoryTree;
use Ergonode\Category\Domain\Repository\TreeRepositoryInterface;
use Ergonode\Channel\Domain\Entity\Export;
use Ergonode\Channel\Domain\Repository\ExportRepositoryInterface;
use Ergonode\Channel\Domain\ValueObject\ExportLineId;
use Ergonode\ExporterShopware6\Domain\Entity\Shopware6Channel;
use Ergonode\ExporterShopware6\Domain\Repository\CategoryRepositoryInterface;
use Ergonode\ExporterShopware6\Domain\Repository\LanguageRepositoryInterface;
use Ergonode\ExporterShopware6\Infrastructure\Builder\CategoryBuilder;
use Ergonode\ExporterShopware6\Infrastructure\Client\Shopware6CategoryClient;
use Ergonode\ExporterShopware6\Infrastructure\Model\Shopware6Category;
use Ergonode\SharedKernel\Domain\Aggregate\CategoryId;
use Ergonode\SharedKernel\Domain\Aggregate\CategoryTreeId;
use GuzzleHttp\Exception\ClientException;
use Webmozart\Assert\Assert;

class CategoryShopware6ExportProcess
{
    private CategoryRepositoryInterface $shopware6CategoryRepository;

    private Shopware6CategoryClient $categoryClient;

    private CategoryBuilder $builder;

    private LanguageRepositoryInterface $languageRepository;

    private ExportRepositoryInterface $exportRepository;

    private TreeRepositoryInterface $treeRepository;

    public function __construct(
        CategoryRepositoryInterface $shopware6CategoryRepository,
        Shopware6CategoryClient $categoryClient,
        CategoryBuilder $builder,
        LanguageRepositoryInterface $languageRepository,
        ExportRepositoryInterface $exportRepository,
        TreeRepositoryInterface $treeRepository
    ) {
        $this->shopware6CategoryRepository = $shopware6CategoryRepository;
        $this->categoryClient = $categoryClient;
        $this->builder = $builder;
        $this->languageRepository = $languageRepository;
        $this->exportRepository = $exportRepository;
        $this->treeRepository = $treeRepository;
    }

    public function process(
        CategoryTreeId $categoryTreeId,
        ExportLineId $lineId,
        Export $export,
        Shopware6Channel $channel,
        AbstractCategory $category,
        ?CategoryId $parentId = null
    ): void {
        $parentShopwareId = null;
        if (!$parentId) {
            // creates a category in Shopware for a tree name
            $parentShopwareId = $this->loadOrCreateCategoryForTree($channel, $categoryTreeId);
        }
        $shopwareCategory = $this->loadCategory($channel, $category, $categoryTreeId);
        if ($shopwareCategory) {
            $this->updateFullCategory($channel, $export, $shopwareCategory, $category, $categoryTreeId,  $parentId, $parentShopwareId);
        } else {
            $shopwareCategory = new Shopware6Category();
            $this->builder->build($channel, $export, $shopwareCategory, $category, $categoryTreeId, $parentId, $parentShopwareId);
            $this->categoryClient->insert($channel, $shopwareCategory, $category->getId(), $categoryTreeId);
        }

        $this->exportRepository->processLine($lineId);
    }

    private function updateFullCategory(
        Shopware6Channel $channel,
        Export $export,
        Shopware6Category $shopwareCategory,
        AbstractCategory $category,
        CategoryTreeId $categoryTreeId,
        ?CategoryId $parentId = null,
        ?string $parentShopwareId = null
    ): void {
        $requireUpdate = false;

        $shopwareLanguage = $this->languageRepository->load(
            $channel->getId(),
            $channel->getDefaultLanguage()->getCode()
        );
        Assert::notNull(
            $shopwareLanguage,
            sprintf('Expected a value other than null for category lang  %s', $channel->getDefaultLanguage()->getCode())
        );
        $shopwareCategory = $this->builder->build(
            $channel,
            $export,
            $shopwareCategory,
            $category,
            $categoryTreeId,
            $parentId,
            $parentShopwareId
        );

        $shopwareCategory->updateTranslated($shopwareCategory, $shopwareLanguage);
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
                $translatedCategory = $this->builder->build(
                    $channel,
                    $export,
                    $translatedCategory,
                    $category,
                    $categoryTreeId,
                    $parentId,
                    $parentShopwareId,
                    $channelLanguage
                );
                $shopwareCategory->updateTranslated($translatedCategory, $shopwareLanguage);

                if ($translatedCategory->isModified()) {
                    $requireUpdate = true;
                }
            }
        }

        if ($requireUpdate) {
            $this->categoryClient->update($channel, $shopwareCategory);
        }
    }

    private function loadCategory(
        Shopware6Channel $channel,
        AbstractCategory $category,
        CategoryTreeId $categoryTreeId
    ): ?Shopware6Category {
        $shopwareId = $this->shopware6CategoryRepository->load($channel->getId(), $category->getId(), $categoryTreeId);
        if ($shopwareId) {
            try {
                return $this->categoryClient->get($channel, $shopwareId);
            } catch (ClientException $exception) {
            }
        }

        return null;
    }

    private function loadOrCreateCategoryForTree(
        Shopware6Channel $channel,
        CategoryTreeId $categoryTreeId
    ): string {
        $categoryId = new CategoryId($categoryTreeId->getValue());
        $shopwareId = $this->shopware6CategoryRepository->load(
            $channel->getId(),
            $categoryId,
            $categoryTreeId
        );
        if (!$shopwareId) {
            $categoryTree = $this->treeRepository->load($categoryTreeId);
            Assert::isInstanceOf($categoryTree, CategoryTree::class);
            $shopwareCategory = new Shopware6Category();
            $shopwareCategory->setName($categoryTree->getName()->get($channel->getDefaultLanguage()));

            $this->categoryClient->insert($channel, $shopwareCategory, $categoryId, $categoryTreeId);
            $shopwareId = $this->shopware6CategoryRepository->load(
                $channel->getId(),
                $categoryId,
                $categoryTreeId
            );

            if (!$shopwareId) {
                throw new \Exception(sprintf('Failed creating category for tree %s', $categoryTreeId->getValue()));
            }
        }

        return $shopwareId;
    }
}
