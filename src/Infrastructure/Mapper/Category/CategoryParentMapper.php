<?php
/**
 * Copyright © Ergonode Sp. z o.o. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Infrastructure\Mapper\Category;

use Ergonode\Category\Domain\Entity\AbstractCategory;
use Ergonode\Core\Domain\ValueObject\Language;
use Ergonode\Channel\Domain\Entity\Export;
use Ergonode\ExporterShopware6\Domain\Entity\Shopware6Channel;
use Ergonode\ExporterShopware6\Domain\Repository\CategoryRepositoryInterface;
use Ergonode\ExporterShopware6\Infrastructure\Mapper\CategoryMapperInterface;
use Ergonode\ExporterShopware6\Infrastructure\Model\Shopware6Category;
use Ergonode\SharedKernel\Domain\Aggregate\CategoryId;
use Ergonode\SharedKernel\Domain\Aggregate\CategoryTreeId;

class CategoryParentMapper implements CategoryMapperInterface
{
    private CategoryRepositoryInterface $repository;

    public function __construct(CategoryRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * {@inheritDoc}
     */
    public function map(
        Shopware6Channel $channel,
        Export $export,
        Shopware6Category $shopware6Category,
        AbstractCategory $category,
        CategoryTreeId $categoryTreeId,
        ?CategoryId $parentCategoryId = null,
        ?string $parentShopwareId = null,
        ?Language $language = null
    ): Shopware6Category {
        if ($parentShopwareId) {
            $shopware6Category->setParentId($parentShopwareId);
            return $shopware6Category;
        }

        if ($parentCategoryId) {
            $shopwareParentId = $this->repository->load($channel->getId(), $parentCategoryId, $categoryTreeId);
            $shopware6Category->setParentId($shopwareParentId);
        }

        return $shopware6Category;
    }
}
