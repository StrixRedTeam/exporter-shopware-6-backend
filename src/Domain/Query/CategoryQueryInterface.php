<?php
/**
 * Copyright © Ergonode Sp. z o.o. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Domain\Query;

use Ergonode\SharedKernel\Domain\Aggregate\CategoryId;
use Ergonode\SharedKernel\Domain\Aggregate\CategoryTreeId;
use Ergonode\SharedKernel\Domain\Aggregate\ChannelId;

interface CategoryQueryInterface
{
    public function loadByShopwareId(ChannelId $channel, string $shopwareId): ?CategoryId;

    public function cleanData(ChannelId $channel, \DateTimeImmutable $dateTime): void;

    /**
     * @param ChannelId $channelId
     * @param string[] $categoryIds
     * @param CategoryTreeId $categoryTreeId
     * @return string[]
     */
    public function getCategoryToDelete(ChannelId $channelId, array $categoryIds, CategoryTreeId $categoryTreeId): array;

    /**
     * @param ChannelId $channelId
     * @param string[] $categoryTreeIds
     * @return array
     */
    public function getCategoryTreesToDelete(ChannelId $channelId, array $categoryTreeIds): array;
}
