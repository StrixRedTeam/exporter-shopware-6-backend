<?php
/**
 * Copyright © Ergonode Sp. z o.o. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Infrastructure\Client;

use Ergonode\ExporterShopware6\Domain\Entity\Shopware6Channel;
use Ergonode\ExporterShopware6\Domain\Repository\PropertyGroupOptionsRepositoryInterface;
use Ergonode\ExporterShopware6\Infrastructure\Connector\Action\CustomField\BatchPostPropertyGroupOptionAction;
use Ergonode\ExporterShopware6\Infrastructure\Connector\Action\PropertyGroup\DeletePropertyGroupOption;
use Ergonode\ExporterShopware6\Infrastructure\Connector\Action\PropertyGroup\GetPropertyGroupOptions;
use Ergonode\ExporterShopware6\Infrastructure\Connector\Action\PropertyGroup\GetPropertyGroupOptionsList;
use Ergonode\ExporterShopware6\Infrastructure\Connector\Action\PropertyGroup\PatchPropertyGroupOptionAction;
use Ergonode\ExporterShopware6\Infrastructure\Connector\Shopware6Connector;
use Ergonode\ExporterShopware6\Infrastructure\Connector\Shopware6QueryBuilder;
use Ergonode\ExporterShopware6\Infrastructure\Model\PropertyGroupOption\BatchPropertyGroupOption;
use Ergonode\ExporterShopware6\Infrastructure\Model\Shopware6Language;
use Ergonode\ExporterShopware6\Infrastructure\Model\Shopware6PropertyGroupOption;
use Ergonode\SharedKernel\Domain\Aggregate\AttributeId;
use Ergonode\SharedKernel\Domain\AggregateId;

class Shopware6PropertyGroupOptionClient
{
    private const ENTITY_NAME = 'property_group_option';
    private const TRANSLATION_ENTITY_NAME = 'property_group_option_translation';

    private Shopware6Connector $connector;

    private PropertyGroupOptionsRepositoryInterface $propertyGroupOptionsRepository;

    public function __construct(
        Shopware6Connector $connector,
        PropertyGroupOptionsRepositoryInterface $propertyGroupOptionsRepository
    ) {
        $this->connector = $connector;
        $this->propertyGroupOptionsRepository = $propertyGroupOptionsRepository;
    }

    /**
     * @return Shopware6PropertyGroupOption[]|null
     */
    public function getAll(Shopware6Channel $channel): ?array
    {
        $query = new Shopware6QueryBuilder();
        $query->limit(1000);
        $query->association('translations', [0 => '']);
        $query->include(self::ENTITY_NAME, ['id', 'name', 'mediaId', 'position']);
        $action = new GetPropertyGroupOptionsList($query);

        return $this->connector->execute($channel, $action);
    }

    /**
     * @param Shopware6Channel $channel
     * @param string $propertyGroupId
     * @param Shopware6Language|null $shopware6Language
     * @return Shopware6PropertyGroupOption[]|null
     * @throws \Exception
     */
    public function get(
        Shopware6Channel $channel,
        string $propertyGroupId,
        ?Shopware6Language $shopware6Language = null
    ) {
        $query = new Shopware6QueryBuilder();
        $query->association('translations', [0 => '']);
        $query->include(self::ENTITY_NAME, ['id', 'name', 'mediaId', 'position', 'groupId']);
        $query->include(self::TRANSLATION_ENTITY_NAME, ['name', 'languageId']);

        $limit = 5000;
        $page = 0;
        $result = [];
        do {
            $action = new GetPropertyGroupOptions($propertyGroupId, $query);
            if ($shopware6Language) {
                $action->addHeader('sw-language-id', $shopware6Language->getId());
            }
            $page++;
            $query->limit($limit);
            $query->setPage($page);
            $options = $this->connector->execute($channel, $action);
            $result = array_merge($result, $options);
        } while (!empty($options));

        return $result;
    }

    public function update(
        Shopware6Channel $channel,
        string $propertyGroupId,
        Shopware6PropertyGroupOption $propertyGroupOption,
        ?Shopware6Language $shopware6Language = null
    ): void {
        $action = new PatchPropertyGroupOptionAction($propertyGroupId, $propertyGroupOption);
        if ($shopware6Language) {
            $action->addHeader('sw-language-id', $shopware6Language->getId());
        }
        $this->connector->execute($channel, $action);
    }

    /**
     * @param Shopware6Channel $channel
     * @param BatchPropertyGroupOption $batchCustomField
     * @return void
     * @throws \Exception
     */
    public function insertBatch(
        Shopware6Channel $channel,
        BatchPropertyGroupOption $batchCustomField
    ): void {
        $action = new BatchPostPropertyGroupOptionAction($batchCustomField);
        $action->addHeader('indexing-behavior', 'use-queue-indexing');

        $ids = $this->connector->execute($channel, $action);

        foreach ($ids as $requestId => $shopwareId) {
            [$attributeId, $optionId] = explode('_', $requestId, 2);
            $this->propertyGroupOptionsRepository->save(
                $channel->getId(),
                new AttributeId($attributeId),
                new AggregateId($optionId),
                $shopwareId
            );
        }
    }

    public function delete(Shopware6Channel $channel, string $shopwareId): void
    {
        $action = new DeletePropertyGroupOption($shopwareId);
        $this->connector->execute($channel, $action);
        $this->propertyGroupOptionsRepository->delete($channel->getId(), $shopwareId);
    }
}
