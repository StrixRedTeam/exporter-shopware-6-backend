<?php
/**
 * Copyright © Ergonode Sp. z o.o. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Infrastructure\Model;

class Shopware6Category implements \JsonSerializable
{
    private ?string $id;

    private ?string $name;

    private ?string $parentId;

    private bool $active;

    private bool $visible;

    protected bool $modified = false;

    private ?array $customFields;

    private ?string $description;

    private ?string $mediaId;

    private ?string $metaTitle;

    private ?string $metaDescription;

    private ?string $keywords;

    public function __construct(
        ?string $id = null,
        ?string $name = null,
        ?string $parentId = null,
        bool $active = true,
        bool $visible = true,
        ?array $customFields = null,
        ?string $description = null,
        ?string $mediaId = null,
        ?string $metaTitle = null,
        ?string $metaDescription = null,
        ?string $keywords = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->parentId = $parentId;
        $this->active = $active;
        $this->visible = $visible;
        $this->customFields = $customFields;
        $this->description = $description;
        $this->mediaId = $mediaId;
        $this->metaTitle = $metaTitle;
        $this->metaDescription = $metaDescription;
        $this->keywords = $keywords;
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

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        if ($this->parentId !== $parentId) {
            $this->parentId = $parentId;
            $this->modified = true;
        }
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        if ($this->active !== $active) {
            $this->active = $active;
            $this->modified = true;
        }
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): void
    {
        if ($this->visible !== $visible) {
            $this->visible = $visible;
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

    public function getMediaId(): ?string
    {
        return $this->mediaId;
    }

    public function setMediaId(?string $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): void
    {
        $this->metaTitle = $metaTitle;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): void
    {
        $this->metaDescription = $metaDescription;
    }

    public function getKeywords(): ?string
    {
        return $this->keywords;
    }

    public function setKeywords(?string $keywords): void
    {
        $this->keywords = $keywords;
    }


    public function jsonSerialize(): array
    {
        $data =
            [
                'name' => $this->name,
                'active' => $this->active,
                'visible' => $this->visible,
                'customFields' => $this->customFields,
            ];
        if (null !== $this->parentId) {
            $data['parentId'] = $this->parentId;
        }

        if (null !== $this->mediaId) {
            $data['mediaId'] = $this->mediaId;
        }
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
}
