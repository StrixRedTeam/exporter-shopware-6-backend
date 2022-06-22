<?php
/**
 * Copyright © Ergonode Sp. z o.o. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Infrastructure\Processor\Process;

use Ergonode\Attribute\Domain\Entity\AbstractAttribute;
use Ergonode\Attribute\Domain\Repository\AttributeRepositoryInterface;
use Ergonode\Channel\Domain\Entity\Export;
use Ergonode\Channel\Domain\Repository\ExportRepositoryInterface;
use Ergonode\Core\Domain\ValueObject\Language;
use Ergonode\ExporterShopware6\Domain\Entity\Shopware6Channel;
use Ergonode\ExporterShopware6\Domain\Query\EventStoreQueryInterface;
use Ergonode\ExporterShopware6\Domain\Query\ExportQueryInterface;
use Ergonode\ExporterShopware6\Domain\Repository\LanguageRepositoryInterface;
use Ergonode\ExporterShopware6\Domain\Repository\PropertyGroupRepositoryInterface;
use Ergonode\ExporterShopware6\Infrastructure\Builder\PropertyGroupBuilder;
use Ergonode\ExporterShopware6\Infrastructure\Client\Shopware6PropertyGroupClient;
use Ergonode\ExporterShopware6\Infrastructure\Exception\Shopware6ExporterException;
use Ergonode\ExporterShopware6\Infrastructure\Model\Shopware6PropertyGroup;
use Webmozart\Assert\Assert;

class PropertyGroupShopware6ExportProcess
{
    protected AttributeRepositoryInterface $attributeRepository;

    private PropertyGroupRepositoryInterface $propertyGroupRepository;

    private Shopware6PropertyGroupClient $propertyGroupClient;

    private PropertyGroupBuilder $builder;

    private LanguageRepositoryInterface $languageRepository;

    private PropertyGroupOptionsShopware6ExportProcess $propertyGroupOptionsProcess;

    private ExportRepositoryInterface $exportRepository;

    private EventStoreQueryInterface $eventStoreQuery;

    private ExportQueryInterface $exportQuery;

    public function __construct(
        PropertyGroupRepositoryInterface $propertyGroupRepository,
        Shopware6PropertyGroupClient $propertyGroupClient,
        PropertyGroupBuilder $builder,
        LanguageRepositoryInterface $languageRepository,
        PropertyGroupOptionsShopware6ExportProcess $propertyGroupOptionsProcess,
        ExportRepositoryInterface $exportRepository,
        AttributeRepositoryInterface $attributeRepository,
        EventStoreQueryInterface $eventStoreQuery,
        ExportQueryInterface $exportQuery
    ) {
        $this->propertyGroupRepository = $propertyGroupRepository;
        $this->propertyGroupClient = $propertyGroupClient;
        $this->builder = $builder;
        $this->languageRepository = $languageRepository;
        $this->propertyGroupOptionsProcess = $propertyGroupOptionsProcess;
        $this->exportRepository = $exportRepository;
        $this->attributeRepository = $attributeRepository;
        $this->eventStoreQuery = $eventStoreQuery;
        $this->exportQuery = $exportQuery;
    }

    /**
     * @throws \Exception
     */
    public function process(
        Export $export,
        Shopware6Channel $channel,
        array $attributeIds
    ): void {
        $lastExportDate = $this->exportQuery->findLastExportStarted($channel->getId());
        $propertyGroups = $this->propertyGroupClient->getAll($channel);
        foreach ($attributeIds as $attributeId) {
            $attribute = $this->attributeRepository->load($attributeId);
            Assert::isInstanceOf($attribute, AbstractAttribute::class);

            $lastAttributeChangeDate = $this->eventStoreQuery->findLastDateForAggregateId($attributeId);
            $skipExport = false;
            // if property group was not changed since last export, skip it
            if ($lastExportDate && $lastAttributeChangeDate && $lastAttributeChangeDate < $lastExportDate) {
                $skipExport = true;
            }

            if (!$skipExport) {
                $shopwareId = $this->propertyGroupRepository->load($channel->getId(), $attribute->getId());
                $propertyGroup = null;
                if ($shopwareId && isset($propertyGroups[$shopwareId])) {
                    $propertyGroup = $propertyGroups[$shopwareId];
                }

                try {
                    if (!$propertyGroup) {
                        $propertyGroup = new Shopware6PropertyGroup();
                    }

                    foreach ($channel->getLanguages() as $language) {
                        if ($this->languageRepository->exists($channel->getId(), $language->getCode())) {
                            $this->buildPropertyGroupWithLanguage(
                                $propertyGroup,
                                $channel,
                                $export,
                                $language,
                                $attribute
                            );
                        }
                    }

                    if (!$propertyGroup->getId()) {
                        $this->propertyGroupClient->insert($channel, $propertyGroup, $attribute);
                    } else {
                        $this->propertyGroupClient->update($channel, $propertyGroup);
                    }
                } catch (Shopware6ExporterException $exception) {
                    $this->exportRepository->addError(
                        $export->getId(),
                        $exception->getMessage(),
                        $exception->getParameters()
                    );
                }
            }

            $this->propertyGroupOptionsProcess->process($export, $channel, $attribute);
        }
    }

    private function buildPropertyGroupWithLanguage(
        Shopware6PropertyGroup $propertyGroup,
        Shopware6Channel $channel,
        Export $export,
        Language $language,
        AbstractAttribute $attribute
    ): void {
        $shopwareLanguage = $this->languageRepository->load($channel->getId(), $language->getCode());
        Assert::notNull($shopwareLanguage,sprintf('Expected a value other than null for property group with lang %s', $language->getCode()));

        $this->builder->build($channel, $export, $propertyGroup, $attribute, $language, $shopwareLanguage);
    }
}
