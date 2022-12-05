<?php
/**
 * Copyright © Ergonode Sp. z o.o. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Infrastructure\Model;

use Ergonode\ExporterShopware6\Infrastructure\Model\Media\Shopware6MediaTranslation;
use JsonSerializable;

class Shopware6Media implements JsonSerializable
{
    private ?string $id;

    protected ?string $fileName;

    private bool $modified = false;

    /**
     * @var Shopware6MediaTranslation[]
     */
    private array $translations = [];

    public function __construct(?string $id, ?string $fileName)
    {
        $this->id = $id;
        $this->fileName = $fileName;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): void
    {
        $this->fileName = $fileName;
    }

    public function jsonSerialize(): array
    {
        $data = [];
        if (!is_null($this->fileName)) {
            $data['fileName'] = $this->fileName;
        }

        foreach ($this->translations as $translation) {
            $data['translations'][$translation->getLanguageId()] = $translation->jsonSerialize();
        }

        return $data;
    }

    public function isModified(): bool
    {
        return $this->modified;
    }

    public function setModified(): void
    {
        $this->modified = true;
    }

    /**
     * @param Shopware6MediaTranslation[] $translations
     */
    public function setTranslations(array $translations): void
    {
        $this->translations = $translations;
    }

    /**
     * @return Shopware6MediaTranslation[]
     */
    public function getTranslations(): array
    {
        return $this->translations;
    }

    /**
     * @param Shopware6MediaTranslation[] $translations
     */
    public function updateTranslated(array $translations): void
    {
        $processedLanguages = [];
        foreach ($translations as $translation) {
            $processedLanguages[] = $translation->getLanguageId();
            if (array_key_exists($translation->getLanguageId(), $this->translations)) {
                if (!$this->isTranslationChanged($translation, $this->translations[$translation->getLanguageId()])) {
                    continue;
                }
                $id = $this->translations[$translation->getLanguageId()]->getId();
                $translation->setId($id);
            }
            $this->translations[$translation->getLanguageId()] = $translation;
            $this->setModified();
        }

        $this->removeNotConfirmedTranslations($processedLanguages);
    }

    /**
     * @param Shopware6MediaTranslation[] $processedLanguages
     */
    private function removeNotConfirmedTranslations(array $processedLanguages): void
    {
        foreach ($this->translations as $language => $translation) {
            if (!in_array($language, $processedLanguages) && !$this->isTranslationEmpty($translation)) {
                $this->translations[$language] = new Shopware6MediaTranslation(
                    $translation->getId(),
                    null,
                    null,
                    $translation->getLanguageId()
                );
                $this->setModified();
            }
        }
    }

    private function isTranslationEmpty(Shopware6MediaTranslation $translation): bool
    {
        return is_null($translation->getTitle()) && is_null($translation->getAlt());
    }

    private function isTranslationChanged(Shopware6MediaTranslation $sample, Shopware6MediaTranslation $translation): bool
    {
        return ($sample->getTitle() !== $translation->getTitle() || $sample->getAlt() !== $translation->getAlt());
    }
}
