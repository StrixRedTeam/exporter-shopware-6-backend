<?php
declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Domain\Query;

use Ergonode\SharedKernel\Domain\AggregateId;

interface EventStoreQueryInterface
{
    public function findLastDateForAggregateId(AggregateId $aggregateId): ?\DateTime;

}
