<?php
/**
 * Copyright © Ergonode Sp. z o.o. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Infrastructure\Processor\Step;

use Ergonode\Category\Domain\Repository\TreeRepositoryInterface;
use Ergonode\Category\Domain\ValueObject\Node;
use Ergonode\SharedKernel\Domain\Aggregate\CategoryTreeId;
use Ergonode\SharedKernel\Domain\Bus\CommandBusInterface;
use Ergonode\ExporterShopware6\Domain\Command\Export\CategoryRemoveExportCommand;
use Ergonode\ExporterShopware6\Domain\Entity\Shopware6Channel;
use Ergonode\ExporterShopware6\Domain\Query\CategoryQueryInterface;
use Ergonode\ExporterShopware6\Infrastructure\Processor\ExportStepProcessInterface;
use Ergonode\SharedKernel\Domain\Aggregate\CategoryId;
use Ergonode\SharedKernel\Domain\Aggregate\ExportId;
use Webmozart\Assert\Assert;

class CategoryRemoveStep implements ExportStepProcessInterface
{
    private TreeRepositoryInterface $treeRepository;

    private CategoryQueryInterface $shopwareCategoryQuery;

    private CommandBusInterface $commandBus;

    public function __construct(
        TreeRepositoryInterface $treeRepository,
        CategoryQueryInterface $shopwareCategoryQuery,
        CommandBusInterface $commandBus
    ) {
        $this->treeRepository = $treeRepository;
        $this->shopwareCategoryQuery = $shopwareCategoryQuery;
        $this->commandBus = $commandBus;
    }

    public function export(ExportId $exportId, Shopware6Channel $channel): void
    {
        $categoryTreeIds = [];
        $categoryTrees = $channel->getCategoryTrees();
        foreach ($categoryTrees as $id) {
            $categoryTreeId = new CategoryTreeId($id);
            $categoryIds = [];
            $tree = $this->treeRepository->load($categoryTreeId);
            Assert::notNull($tree, sprintf('Tree %s not exists', $categoryTreeId));

            foreach ($tree->getCategories() as $node) {
                $newCategoryIds = $this->buildStep($node);
                $categoryIds = array_unique(array_merge($categoryIds, $newCategoryIds));
            }
            // tree is also sent to Shopware as category
            $categoryIds[] = $tree->getId()->getValue();
            $categoryTreeIds[] = $tree->getId()->getValue();

            $this->categoryDelete($exportId, $channel, $categoryIds, $categoryTreeId);
        }

        $this->categoryTreeDelete($exportId, $channel, $categoryTreeIds);
    }

    /**
     * @param ExportId $exportId
     * @param Shopware6Channel $channel
     * @param array $categoryIds
     * @param CategoryTreeId $categoryTreeId
     */
    private function categoryDelete(ExportId $exportId, Shopware6Channel $channel, array $categoryIds, CategoryTreeId $categoryTreeId): void
    {
        $categoryList = $this->shopwareCategoryQuery->getCategoryToDelete(
            $channel->getId(),
            $categoryIds,
            $categoryTreeId
        );

        foreach ($categoryList as $category) {
            $categoryId = new CategoryId($category);
            $processCommand = new CategoryRemoveExportCommand($exportId, $categoryId, $categoryTreeId);
            $this->commandBus->dispatch($processCommand, true);
        }
    }

    /**
     * @param ExportId $exportId
     * @param Shopware6Channel $channel
     * @param array $categoryTreeIds
     */
    private function categoryTreeDelete(ExportId $exportId, Shopware6Channel $channel, array $categoryTreeIds): void
    {
        $categoryList = $this->shopwareCategoryQuery->getCategoryTreesToDelete(
            $channel->getId(),
            $categoryTreeIds
        );

        foreach ($categoryList as $categoryRow) {
            $categoryId = new CategoryId($categoryRow['category_id']);
            $processCommand = new CategoryRemoveExportCommand(
                $exportId,
                $categoryId,
                new CategoryTreeId($categoryRow['category_tree_id'])
            );
            $this->commandBus->dispatch($processCommand, true);
        }
    }

    /**
     * @return array
     */
    private function buildStep(Node $node): array
    {
        $categoryIds[] = $node->getCategoryId()->getValue();
        foreach ($node->getChildren() as $child) {
            $newCategoryIds = $this->buildStep($child);
            $categoryIds = array_unique(array_merge($categoryIds, $newCategoryIds));
        }

        return $categoryIds;
    }
}
