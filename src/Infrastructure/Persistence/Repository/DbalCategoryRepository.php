<?php

/**
 * Copyright © Ergonode Sp. z o.o. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Infrastructure\Persistence\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Ergonode\ExporterShopware6\Domain\Repository\CategoryRepositoryInterface;
use Ergonode\SharedKernel\Domain\Aggregate\CategoryId;
use Ergonode\SharedKernel\Domain\Aggregate\CategoryTreeId;
use Ergonode\SharedKernel\Domain\Aggregate\ChannelId;

class DbalCategoryRepository implements CategoryRepositoryInterface
{
    private const TABLE = 'exporter.shopware6_category';
    private const FIELDS = [
        'channel_id',
        'category_id',
        'shopware6_id',
        'category_tree_id',
    ];

    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function load(ChannelId $channelId, CategoryId $categoryId, ?CategoryTreeId $categoryTreeId = null): ?string
    {
        $qb = $this->connection->createQueryBuilder();
        $query = $qb
            ->select(self::FIELDS)
            ->from(self::TABLE, 'cs')
            ->where($qb->expr()->eq('channel_id', ':channelId'))
            ->setParameter(':channelId', $channelId->getValue())
            ->andWhere($qb->expr()->eq('cs.category_id', ':categoryId'))
            ->setParameter(':categoryId', $categoryId->getValue());

        if ($categoryTreeId) {
            $qb
                ->andWhere($qb->expr()->eq('cs.category_tree_id', ':categoryTreeId'))
                ->setParameter(':categoryTreeId', $categoryTreeId->getValue())
                ->execute();
        }
        $record = $query->execute()->fetch();

        if ($record) {
            return ($record['shopware6_id']);
        }

        return null;
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    public function save(ChannelId $channelId, CategoryId $categoryId, CategoryTreeId $categoryTreeId, string $shopwareId): void
    {
        $sql = 'INSERT INTO '.self::TABLE.' (channel_id, category_id, shopware6_id,category_tree_id, update_at) 
        VALUES (:channelId, :categoryId, :shopware6Id,:categoryTreeId, :updatedAt)
            ON CONFLICT ON CONSTRAINT shopware6_category_pkey
                DO UPDATE SET shopware6_id = :shopware6Id, update_at = :updatedAt
        ';

        $this->connection->executeQuery(
            $sql,
            [
                'channelId' => $channelId->getValue(),
                'categoryId' => $categoryId->getValue(),
                'shopware6Id' => $shopwareId,
                'categoryTreeId' => $categoryTreeId->getValue(),
                'updatedAt' => new \DateTimeImmutable(),
            ],
            [
                'updatedAt' => Types::DATETIMETZ_MUTABLE,
            ]
        );
    }

    public function exists(
        ChannelId $channelId,
        CategoryId $categoryId
    ): bool {
        $query = $this->connection->createQueryBuilder();
        $result = $query->select(1)
            ->from(self::TABLE)
            ->where($query->expr()->eq('channel_id', ':channelId'))
            ->setParameter(':channelId', $channelId->getValue())
            ->andWhere($query->expr()->eq('category_id', ':categoryId'))
            ->setParameter(':categoryId', $categoryId->getValue())
            ->execute()
            ->rowCount();


        if ($result) {
            return true;
        }

        return false;
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\DBAL\Exception\InvalidArgumentException
     */
    public function delete(ChannelId $channelId, CategoryId $categoryId): void
    {
        $this->connection->delete(
            self::TABLE,
            [
                'category_id' => $categoryId->getValue(),
                'channel_id' => $channelId->getValue(),
            ]
        );
    }
}
