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
use Ergonode\ExporterShopware6\Domain\Repository\LanguageRepositoryInterface;
use Ergonode\ExporterShopware6\Domain\Repository\PropertyGroupRepositoryInterface;
use Ergonode\ExporterShopware6\Infrastructure\Builder\PropertyGroupBuilder;
use Ergonode\ExporterShopware6\Infrastructure\Client\Shopware6PropertyGroupClient;
use Ergonode\ExporterShopware6\Infrastructure\Exception\Shopware6ExporterException;
use Ergonode\ExporterShopware6\Infrastructure\Model\Shopware6Language;
use Ergonode\ExporterShopware6\Infrastructure\Model\Shopware6PropertyGroup;
use GuzzleHttp\Exception\ClientException;
use Webmozart\Assert\Assert;

class PropertyGroupShopware6ExportProcess
{
    protected AttributeRepositoryInterface $attributeRepository;

    private PropertyGroupRepositoryInterface $propertyGroupRepository;

    private Shopware6PropertyGroupClient $propertyGroupClient;

    private PropertyGroupBuilder $builder;

    private LanguageRepositoryInterface  $languageRepository;

    private PropertyGroupOptionsShopware6ExportProcess $propertyGroupOptionsProcess;

    private ExportRepositoryInterface $exportRepository;

    public function __construct(
        PropertyGroupRepositoryInterface $propertyGroupRepository,
        Shopware6PropertyGroupClient $propertyGroupClient,
        PropertyGroupBuilder $builder,
        LanguageRepositoryInterface $languageRepository,
        PropertyGroupOptionsShopware6ExportProcess $propertyGroupOptionsProcess,
        ExportRepositoryInterface $exportRepository,
        AttributeRepositoryInterface $attributeRepository
    ) {
        $this->propertyGroupRepository = $propertyGroupRepository;
        $this->propertyGroupClient = $propertyGroupClient;
        $this->builder = $builder;
        $this->languageRepository = $languageRepository;
        $this->propertyGroupOptionsProcess = $propertyGroupOptionsProcess;
        $this->exportRepository = $exportRepository;
        $this->attributeRepository = $attributeRepository;
    }

    /**
     * @throws \Exception
     */
    public function process(
        Export $export,
        Shopware6Channel $channel,
        array $attributeIds
    ): void {
        $propertyGroups = $this->propertyGroupClient->getAll($channel);
        foreach($attributeIds as $attributeId) {
            $attribute = $this->attributeRepository->load($attributeId);
            Assert::isInstanceOf(AbstractAttribute::class, $attribute);


            $shopwareId = $this->propertyGroupRepository->load($channel->getId(), $attribute->getId());
            $propertyGroup = null;
            if ($shopwareId && isset($propertyGroups[$shopwareId])) {
                $propertyGroup = $propertyGroups[$shopwareId];
            }
            //$propertyGroup = $this->loadPropertyGroup($channel, $attribute);
            try {
                if (!$propertyGroup) {
                    $propertyGroup = new Shopware6PropertyGroup();
                }

                //$this->builder->build($channel, $export, $propertyGroup, $attribute, $language);
                //    $this->builder->build($channel, $export, $propertyGroup, $attribute);
                //}

                foreach ($channel->getLanguages() as $language) {
                    if ($this->languageRepository->exists($channel->getId(), $language->getCode())) {
                        $this->buildPropertyGroupWithLanguage($propertyGroup, $channel, $export, $language, $attribute);
                    }
                }
                $this->propertyGroupClient->insert($channel, $propertyGroup, $attribute);
                //$this->propertyGroupOptionsProcess->process($export, $channel, $attribute);
            } catch (Shopware6ExporterException $exception) {
                $this->exportRepository->addError(
                    $export->getId(),
                    $exception->getMessage(),
                    $exception->getParameters()
                );
            }
            return;
        }
    }

    private function updatePropertyGroup(
        Shopware6Channel $channel,
        Export $export,
        Shopware6PropertyGroup $propertyGroup,
        AbstractAttribute $attribute,
        ?Language $language = null,
        ?Shopware6Language $shopwareLanguage = null
    ): void {
        if ($propertyGroup->isModified()) {
            $this->propertyGroupClient->update($channel, $propertyGroup, $shopwareLanguage);
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
        Assert::notNull($shopwareLanguage);

        $this->builder->build($channel, $export, $propertyGroup, $attribute, $language, $shopwareLanguage);
    }

    private function updatePropertyGroupWithLanguage(
        Shopware6Channel $channel,
        Export $export,
        Language $language,
        AbstractAttribute $attribute
    ): void {
        $shopwareLanguage = $this->languageRepository->load($channel->getId(), $language->getCode());
        Assert::notNull($shopwareLanguage);

        $shopwarePropertyGroup = $this->loadPropertyGroup($channel, $attribute, $shopwareLanguage);
        Assert::notNull($shopwarePropertyGroup);

        $this->updatePropertyGroup($channel, $export, $shopwarePropertyGroup, $attribute, $language, $shopwareLanguage);
    }

    private function loadPropertyGroup(
        Shopware6Channel $channel,
        AbstractAttribute $attribute,
        ?Shopware6Language $shopware6Language = null
    ): ?Shopware6PropertyGroup {
        $shopwareId = $this->propertyGroupRepository->load($channel->getId(), $attribute->getId());
        if ($shopwareId) {
            try {
                return $this->propertyGroupClient->get($channel, $shopwareId, $shopware6Language);
            } catch (ClientException $exception) {
            }
        }

        return null;
    }
}
