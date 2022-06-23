<?php
declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Infrastructure\Model\Product;

class Shopware6SeoUrl implements \JsonSerializable
{
    private ?string $id;

    private string $seoPathInfo;

    private string $salesChannelId;

    public function __construct(?string $id = null, string $seoPathInfo, string $salesChannelId)
    {
        $this->id = $id;
        $this->seoPathInfo = $seoPathInfo;
        $this->salesChannelId = $salesChannelId;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function jsonSerialize(): array
    {
        if ($this->id) {
            return [
                'id' => $this->id,
                'salesChannelId' => $this->salesChannelId,
                'seoPathInfo' => $this->seoPathInfo,
            ];
        }

        return [
            'salesChannelId' => $this->salesChannelId,
            'seoPathInfo' => $this->seoPathInfo,
        ];
    }

    public function getSeoPathInfo(): string
    {
        return $this->seoPathInfo;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }
}
