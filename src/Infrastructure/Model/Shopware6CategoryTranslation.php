<?php
declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Infrastructure\Model;

class Shopware6CategoryTranslation implements \JsonSerializable
{
    private ?string $id;

    private ?string $name;

    private ?array $customFields;

    private ?string $description;

    private ?string $metaTitle;

    private ?string $metaDescription;

    private ?string $keywords;

    private bool $modified = false;

    private string $languageId;

    public function __construct(
        ?string $id = null,
        ?string $name = null,
        ?array $customFields = null,
        ?string $description = null,
        ?string $metaTitle = null,
        ?string $metaDescription = null,
        ?string $keywords = null,
        string $languageId
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->customFields = $customFields;
        $this->description = $description;
        $this->metaTitle = $metaTitle;
        $this->metaDescription = $metaDescription;
        $this->keywords = $keywords;
        $this->languageId = $languageId;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        if ($this->name !== $name) {
            $this->name = $name;
            $this->modified = true;
        }
    }

    public function isModified(): bool
    {
        return $this->modified;
    }

    /**
     * @return array|null
     */
    public function getCustomFields(): ?array
    {
        if ($this->customFields) {
            return $this->customFields;
        }

        return [];
    }

    /**
     * @param string|array|null $value
     */
    public function addCustomField(string $customFieldId, $value): void
    {
        if ($this->hasCustomField($customFieldId)) {
            if ($this->customFields[$customFieldId] !== $value) {
                $this->customFields[$customFieldId] = $value;
                $this->modified = true;
            }
        } else {
            $this->customFields[$customFieldId] = $value;
            $this->modified = true;
        }
    }

    public function hasCustomField(string $customFieldId): bool
    {
        foreach (array_keys($this->getCustomFields()) as $key) {
            if ($key === $customFieldId) {
                return true;
            }
        }

        return false;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): void
    {
        $this->metaTitle = $metaTitle;
        $this->modified = true;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): void
    {
        $this->metaDescription = $metaDescription;
        $this->modified = true;
    }

    public function getKeywords(): ?string
    {
        return $this->keywords;
    }

    public function setKeywords(?string $keywords): void
    {
        $this->keywords = $keywords;
        $this->modified = true;
    }

    public function jsonSerialize(): array
    {
        $data = [
            'name'         => $this->name,
            'customFields' => $this->customFields,
            'languageId' => $this->languageId
        ];
        if (null !== $this->metaTitle) {
            $data['metaTitle'] = $this->metaTitle;
        }
        if (null !== $this->metaDescription) {
            $data['metaDescription'] = $this->metaDescription;
        }
        if (null !== $this->keywords) {
            $data['keywords'] = $this->keywords;
        }
        if (null !== $this->description) {
            $data['description'] = $this->description;
        }

        return $data;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }
}
