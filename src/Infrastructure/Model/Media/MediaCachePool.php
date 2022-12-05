<?php

declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Infrastructure\Model\Media;

use Symfony\Component\Cache\Adapter\AdapterInterface;

class MediaCachePool
{
    public const MEDIA_CACHE_KEY = 'media_translations';
    
    private AdapterInterface $filesystemAdapter;

    public function __construct(
        AdapterInterface $filesystemAdapter
    ) {
        $this->filesystemAdapter = $filesystemAdapter;
    }

    public function isAvailable(): bool
    {
        return $this->getItem()->isHit();
    }

    public function addData(string $multimediaId, string $shopwareId): void
    {
        $cacheData = $this->getData();
        if (!key_exists($multimediaId, $cacheData)) {
            $cacheData[$multimediaId] = $shopwareId;
            $this->setData($cacheData);
        }
    }

    public function getData(): array
    {
        if (!$this->filesystemAdapter->hasItem(self::MEDIA_CACHE_KEY)) {
            return [];
        }

        return $this->getItem()->get();
    }

    public function deleteCache(): void
    {
        $this->filesystemAdapter->deleteItem(self::MEDIA_CACHE_KEY);
    }

    private function setData(array $data): void
    {
        $cachedData = $this->getItem();
        $cachedData->set($data);
        $this->filesystemAdapter->save($cachedData);
    }

    private function getItem()
    {
        return $this->filesystemAdapter->getItem(self::MEDIA_CACHE_KEY);
    }
}