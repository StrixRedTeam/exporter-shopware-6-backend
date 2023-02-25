<?php
/**
 * Copyright © Ergonode Sp. z o.o. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Domain\Command\Export;

use Ergonode\Channel\Domain\Command\ExporterCommandInterface;
use Ergonode\Channel\Domain\ValueObject\ExportLineId;
use Ergonode\SharedKernel\Domain\Aggregate\CategoryId;
use Ergonode\SharedKernel\Domain\Aggregate\CategoryTreeId;
use Ergonode\SharedKernel\Domain\Aggregate\ExportId;

class CategoryExportCommand implements ExporterCommandInterface
{
    private ExportLineId $lineId;

    private ExportId $exportId;

    private CategoryId $categoryId;

    private ?CategoryId $parentCategoryId;

    private CategoryTreeId $categoryTreeId;

    public function __construct(
        CategoryTreeId $categoryTreeId,
        ExportLineId $lineId,
        ExportId $exportId,
        CategoryId $categoryId,
        ?CategoryId $parentCategoryId = null
    ) {
        $this->lineId = $lineId;
        $this->exportId = $exportId;
        $this->categoryId = $categoryId;
        $this->parentCategoryId = $parentCategoryId;
        $this->categoryTreeId = $categoryTreeId;
    }

    public function getLineId(): ExportLineId
    {
        return $this->lineId;
    }

    public function getExportId(): ExportId
    {
        return $this->exportId;
    }

    public function getCategoryId(): CategoryId
    {
        return $this->categoryId;
    }

    public function getParentCategoryId(): ?CategoryId
    {
        return $this->parentCategoryId;
    }

    public function getCategoryTreeId(): CategoryTreeId
    {
        return $this->categoryTreeId;
    }
}
