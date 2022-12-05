<?php

declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Infrastructure\Model\Media;

class Shopware6MediaTranslation implements \JsonSerializable
{
    private ?string $id;
    private ?string $alt;
    private ?string $title;
    private string $languageId;

    public function __construct(
        ?string $id,
        ?string $alt,
        ?string $title,
        string $languageId
    ) {
        $this->id = $id;
        $this->alt = $alt;
        $this->title = $title;
        $this->languageId = $languageId;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setAlt(?string $alt): void
    {
        $this->alt = $alt;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function jsonSerialize(): array
    {
        return [
            'alt' => $this->alt,
            'title' => $this->title,
        ];
    }
}
