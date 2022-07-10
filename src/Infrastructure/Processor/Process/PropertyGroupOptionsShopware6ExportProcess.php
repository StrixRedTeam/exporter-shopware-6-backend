<?php
/**
 * Copyright © Ergonode Sp. z o.o. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Infrastructure\Processor\Process;

use Ergonode\Attribute\Domain\Entity\AbstractAttribute;
use Ergonode\Attribute\Domain\Entity\AbstractOption;
use Ergonode\Attribute\Domain\Query\OptionQueryInterface;
use Ergonode\Attribute\Domain\Repository\OptionRepositoryInterface;
use Ergonode\Channel\Domain\Entity\Export;
use Ergonode\Core\Domain\ValueObject\Language;
use Ergonode\ExporterShopware6\Domain\Entity\Shopware6Channel;
use Ergonode\ExporterShopware6\Domain\Query\EventStoreQueryInterface;
use Ergonode\ExporterShopware6\Domain\Query\ExportQueryInterface;
use Ergonode\ExporterShopware6\Domain\Repository\LanguageRepositoryInterface;
use Ergonode\ExporterShopware6\Domain\Repository\PropertyGroupOptionsRepositoryInterface;
use Ergonode\ExporterShopware6\Domain\Repository\PropertyGroupRepositoryInterface;
use Ergonode\ExporterShopware6\Infrastructure\Builder\PropertyGroupOptionBuilder;
use Ergonode\ExporterShopware6\Infrastructure\Client\Shopware6PropertyGroupOptionClient;
use Ergonode\ExporterShopware6\Infrastructure\Model\PropertyGroupOption\BatchPropertyGroupOption;
use Ergonode\ExporterShopware6\Infrastructure\Model\Shopware6PropertyGroupOption;
use Ergonode\SharedKernel\Domain\AggregateId;
use Webmozart\Assert\Assert;

class PropertyGroupOptionsShopware6ExportProcess
{
    private const CHUNK_SIZE = 30;

    protected PropertyGroupRepositoryInterface $propertyGroupRepository;

    protected OptionQueryInterface $optionQuery;

    private PropertyGroupOptionsRepositoryInterface $propertyGroupOptionsRepository;

    private Shopware6PropertyGroupOptionClient $propertyGroupOptionClient;

    private PropertyGroupOptionBuilder $builder;

    private OptionRepositoryInterface $optionRepository;

    private LanguageRepositoryInterface $languageRepository;

    private EventStoreQueryInterface $eventStoreQuery;

    private ExportQueryInterface $exportQuery;

    public function __construct(
        PropertyGroupRepositoryInterface $propertyGroupRepository,
        OptionQueryInterface $optionQuery,
        PropertyGroupOptionsRepositoryInterface $propertyGroupOptionsRepository,
        Shopware6PropertyGroupOptionClient $propertyGroupOptionClient,
        PropertyGroupOptionBuilder $builder,
        OptionRepositoryInterface $optionRepository,
        LanguageRepositoryInterface $languageRepository,
        EventStoreQueryInterface $eventStoreQuery,
        ExportQueryInterface $exportQuery
    ) {
        $this->propertyGroupRepository = $propertyGroupRepository;
        $this->optionQuery = $optionQuery;
        $this->propertyGroupOptionsRepository = $propertyGroupOptionsRepository;
        $this->propertyGroupOptionClient = $propertyGroupOptionClient;
        $this->builder = $builder;
        $this->optionRepository = $optionRepository;
        $this->languageRepository = $languageRepository;
        $this->eventStoreQuery = $eventStoreQuery;
        $this->exportQuery = $exportQuery;
    }

    public function process(Export $export, Shopware6Channel $channel, AbstractAttribute $attribute): void
    {
        $lastExportDate = $this->exportQuery->findLastExportStarted($channel->getId());
        $propertyGroupId = $this->propertyGroupRepository->load($channel->getId(), $attribute->getId());
        Assert::notNull($propertyGroupId,sprintf('Expected a value other than null for property group %s', $attribute->getId()->getValue()));

        $options = $this->optionQuery->getOptions($attribute->getId());

        $shopwareOptions = $this->propertyGroupOptionClient->get($channel, $propertyGroupId);

        $propertyGroupOptions = [];

        // if no property group option was not changed since last export, skip whole export
        // otherwise export all
        $skipExport = true;
        foreach ($options as $option) {

            $optionId = new AggregateId($option);
            $option = $this->optionRepository->load($optionId);
            Assert::notNull($option,sprintf('Expected a value other than null for option %s', $optionId));

            if ($skipExport) {
                $lastAttributeChangeDate = $this->eventStoreQuery->findLastDateForAggregateId($optionId);
                if (!($lastExportDate && $lastAttributeChangeDate && $lastAttributeChangeDate < $lastExportDate)) {
                    $skipExport = false;
                }
            }

            $shopwareId = $this->propertyGroupOptionsRepository->load(
                $channel->getId(),
                $attribute->getId(),
                $optionId
            );

            $propertyGroupOption = null;
            if ($shopwareId && isset($shopwareOptions[$shopwareId])) {
                $propertyGroupOption = $shopwareOptions[$shopwareId];
                // unset so remaining options could be removed from shopware
                unset($shopwareOptions[$shopwareId]);
            }

            if (!$propertyGroupOption) {
                $propertyGroupOption = new Shopware6PropertyGroupOption();
                $propertyGroupOption->setGroupId($propertyGroupId);
            }

            $this->builder->build($channel, $export, $propertyGroupOption, $option);

            foreach ($channel->getLanguages() as $language) {
                if ($this->languageRepository->exists($channel->getId(), $language->getCode())) {
                    $this->buildPropertyGroupOptionWithLanguage($propertyGroupOption, $channel, $export, $language, $option);
                }
            }

            $requestName = sprintf('%s_%s', $attribute->getId()->getValue(), $optionId->getValue());
            $propertyGroupOption->setRequestName($requestName);

            $propertyGroupOptions[] = $propertyGroupOption;
        }

        if (!$skipExport) {
            $optionsChunk = array_chunk($propertyGroupOptions, self::CHUNK_SIZE);
            foreach ($optionsChunk as $row) {
                $this->propertyGroupOptionClient->insertBatch(
                    $channel,
                    new BatchPropertyGroupOption($row)
                );
            }
        }
        // delete remaining options not existing in Ergonode
        //if (!empty($shopwareOptions)) {
        //    foreach ($shopwareOptions as $shopwareId => $option) {
        //        $this->propertyGroupOptionClient->delete($channel, $shopwareId);
        //    }
        //}
    }

    private function buildPropertyGroupOptionWithLanguage(
        Shopware6PropertyGroupOption $propertyGroupOption,
        Shopware6Channel $channel,
        Export $export,
        Language $language,
        AbstractOption $option
    ): void {
        $shopwareLanguage = $this->languageRepository->load($channel->getId(), $language->getCode());
        Assert::notNull($shopwareLanguage,sprintf('Expected a value other than null for lang property group option %s', $language->getCode()));

        $this->builder->build($channel, $export, $propertyGroupOption, $option, $language, $shopwareLanguage);
    }
}
