<?php
/**
 * Copyright © Ergonode Sp. z o.o. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Domain\Command\Export;

use Ergonode\Channel\Domain\Command\ExporterCommandInterface;
use Ergonode\SharedKernel\Domain\Aggregate\CategoryId;
use Ergonode\SharedKernel\Domain\Aggregate\CategoryTreeId;
use Ergonode\SharedKernel\Domain\Aggregate\ExportId;

class CategoryRemoveExportCommand implements ExporterCommandInterface
{
    private ExportId $exportId;

    private CategoryId $categoryId;

    private CategoryTreeId $categoryTreeId;

    public function __construct(ExportId $exportId, CategoryId $categoryId, CategoryTreeId $categoryTreeId)
    {
        $this->exportId = $exportId;
        $this->categoryId = $categoryId;
        $this->categoryTreeId = $categoryTreeId;
    }

    public function getExportId(): ExportId
    {
        return $this->exportId;
    }

    public function getCategoryId(): CategoryId
    {
        return $this->categoryId;
    }

    public function getCategoryTreeId(): CategoryTreeId
    {
        return $this->categoryTreeId;
    }
}
