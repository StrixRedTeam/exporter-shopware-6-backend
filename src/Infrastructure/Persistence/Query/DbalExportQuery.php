<?php

declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Infrastructure\Persistence\Query;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Ergonode\Channel\Domain\ValueObject\ExportStatus;
use Ergonode\ExporterShopware6\Domain\Query\ExportQueryInterface;
use Ergonode\SharedKernel\Domain\Aggregate\ChannelId;

class DbalExportQuery implements ExportQueryInterface
{
    private const TABLE = 'exporter.export';

    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function findLastExportStarted(ChannelId $channelId): ?\DateTime
    {
        $qb = $this->getQuery();
        $result = $qb
            ->andWhere($qb->expr()->eq('e.channel_id', ':channelId'))
            ->andWhere($qb->expr()->eq('e.status', ':status'))
            ->setParameter(':status', ExportStatus::ENDED)
            ->setParameter(':channelId', $channelId->getValue())
            ->orderBy('e.started_at', 'DESC')
            ->setMaxResults(1)
            ->execute()
            ->fetchAssociative();

        if ($result) {
            return new \DateTime($result['started_at']);
        }

        return null;
    }

    public function isLastExportFinished(ChannelId $channelId): bool
    {
        $qb = $this->getQuery();
        $result = $qb
            ->andWhere($qb->expr()->eq('e.channel_id', ':channelId'))
            ->setParameter(':channelId', $channelId->getValue())
            ->orderBy('e.started_at', 'DESC')
            ->setMaxResults(1)
            ->execute()
            ->fetchAssociative();

        if ($result && isset($result['status'])) {
            return $result['status'] === ExportStatus::ENDED;
        }

        return true;
    }

    private function getQuery(): QueryBuilder
    {
        return $this->connection
            ->createQueryBuilder()
            ->select('e.id, e.status, e.started_at, e.ended_at')
            ->from(self::TABLE, 'e');
    }
}
