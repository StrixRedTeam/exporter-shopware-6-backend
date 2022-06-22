<?php

declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Infrastructure\Persistence\Query;

use Doctrine\DBAL\Connection;
use Ergonode\ExporterShopware6\Domain\Query\EventStoreQueryInterface;
use Ergonode\SharedKernel\Domain\AggregateId;

class DbalEventStoreQuery implements EventStoreQueryInterface
{
    private const TABLE = 'public.event_store';

    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function findLastDateForAggregateId(AggregateId $aggregateId): ?\DateTime
    {
        $query = $this->connection->createQueryBuilder();

        $result =  $query
            ->select('esh.recorded_at')
            ->from(self::TABLE, 'esh')
            ->where($query->expr()->eq('esh.aggregate_id', ':aggregateId'))
            ->setParameter(':aggregateId', $aggregateId->getValue())
            ->orderBy('esh.recorded_at', 'DESC')
            ->execute()
            ->fetchAssociative();

        return ($result && $result['recorded_at']) ? new \DateTime($result['recorded_at']) : null;
    }
}
