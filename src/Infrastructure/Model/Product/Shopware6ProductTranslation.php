<?php

declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Infrastructure\Model\Product;

class Shopware6ProductTranslation implements \JsonSerializable
{
    private ?string $id;
    private ?string $metaDescription;
    private ?string $name;
    private ?string $keywords;
    private ?string $description;
    private ?string $metaTitle;
    private ?array $customFields;
    private string $languageId;

    public function __construct(
        ?string $id,
        ?string $metaDescription,
        ?string $name,
        ?string $keywords,
        ?string $description,
        ?string $metaTitle,
        ?array $customFields,
        string $languageId
    ) {
        $this->id = $id;
        $this->metaDescription = $metaDescription;
        $this->name = $name;
        $this->keywords = $keywords;
        $this->description = $description;
        $this->metaTitle = $metaTitle;
        $this->customFields = $customFields;
        $this->languageId = $languageId;
    }

    public function getId(): ?string
    {
        return $this->id;
    }


    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getKeywords(): ?string
    {
        return $this->keywords;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setMetaDescription(?string $metaDescription): void
    {
        $this->metaDescription = $metaDescription;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function setKeywords(?string $keywords): void
    {
        $this->keywords = $keywords;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function setMetaTitle(?string $metaTitle): void
    {
        $this->metaTitle = $metaTitle;
    }


    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function jsonSerialize(): array
    {
        return [
            'metaDescription' => $this->metaDescription,
            'metaTitle' => $this->metaTitle,
            'name' => $this->name,
            'description' => $this->description,
            'keywords' => $this->keywords,
            'customFields' => $this->customFields
        ];
    }
}
