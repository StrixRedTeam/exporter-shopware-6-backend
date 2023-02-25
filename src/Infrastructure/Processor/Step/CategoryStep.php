<?php
/**
 * Copyright © Ergonode Sp. z o.o. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Infrastructure\Processor\Step;

use Ergonode\Category\Domain\Entity\CategoryTree;
use Ergonode\Category\Domain\Repository\TreeRepositoryInterface;
use Ergonode\Category\Domain\ValueObject\Node;
use Ergonode\Channel\Domain\Repository\ExportRepositoryInterface;
use Ergonode\Channel\Domain\ValueObject\ExportLineId;
use Ergonode\SharedKernel\Domain\Aggregate\CategoryTreeId;
use Ergonode\SharedKernel\Domain\Bus\CommandBusInterface;
use Ergonode\ExporterShopware6\Domain\Command\Export\CategoryExportCommand;
use Ergonode\ExporterShopware6\Domain\Entity\Shopware6Channel;
use Ergonode\ExporterShopware6\Infrastructure\Processor\ExportStepProcessInterface;
use Ergonode\SharedKernel\Domain\Aggregate\CategoryId;
use Ergonode\SharedKernel\Domain\Aggregate\ExportId;
use Webmozart\Assert\Assert;

class CategoryStep implements ExportStepProcessInterface
{
    private TreeRepositoryInterface $treeRepository;

    private CommandBusInterface $commandBus;

    private ExportRepositoryInterface $exportRepository;

    public function __construct(
        TreeRepositoryInterface $treeRepository,
        CommandBusInterface $commandBus,
        ExportRepositoryInterface $exportRepository
    ) {
        $this->treeRepository = $treeRepository;
        $this->commandBus = $commandBus;
        $this->exportRepository = $exportRepository;
    }

    public function export(ExportId $exportId, Shopware6Channel $channel): void
    {
        $categoryTreeIds = $channel->getCategoryTrees();
        foreach ($categoryTreeIds as $categoryTreeId) {
            /** @var CategoryTree $tree */
            $tree = $this->treeRepository->load(new CategoryTreeId($categoryTreeId));
            Assert::notNull($tree, sprintf('Tree %s not exists', $categoryTreeId));
            foreach ($tree->getCategories() as $node) {
                $this->buildStep($tree->getId(), $exportId, $node);
            }
        }
    }

    private function buildStep(CategoryTreeId $categoryTreeId, ExportId $exportId, Node $node, CategoryId $parentId = null): void
    {
        $lineId = ExportLineId::generate();
        $processCommand = new CategoryExportCommand($categoryTreeId, $lineId, $exportId, $node->getCategoryId(), $parentId);
        $this->exportRepository->addLine($lineId, $exportId, $node->getCategoryId());
        $this->commandBus->dispatch($processCommand, true);

        $newParent = $node->getCategoryId();
        foreach ($node->getChildren() as $child) {
            $this->buildStep($categoryTreeId, $exportId, $child, $newParent);
        }
    }
}
