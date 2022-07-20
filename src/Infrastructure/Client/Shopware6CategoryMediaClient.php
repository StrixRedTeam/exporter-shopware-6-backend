<?php

declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Infrastructure\Client;

use Ergonode\ExporterShopware6\Domain\Entity\Shopware6Channel;
use Ergonode\ExporterShopware6\Domain\Repository\MultimediaRepositoryInterface;
use Ergonode\ExporterShopware6\Infrastructure\Connector\Action\Media\DeleteMedia;
use Ergonode\ExporterShopware6\Infrastructure\Connector\Action\Media\GetMediaByFilename;
use Ergonode\ExporterShopware6\Infrastructure\Connector\Action\Media\GetMediaDefaultFolderList;
use Ergonode\ExporterShopware6\Infrastructure\Connector\Action\Media\HasMedia;
use Ergonode\ExporterShopware6\Infrastructure\Connector\Action\Media\PostCreateMediaAction;
use Ergonode\ExporterShopware6\Infrastructure\Connector\Action\Media\PostUploadFile;
use Ergonode\ExporterShopware6\Infrastructure\Connector\Shopware6Connector;
use Ergonode\ExporterShopware6\Infrastructure\Connector\Shopware6QueryBuilder;
use Ergonode\ExporterShopware6\Infrastructure\Exception\Shopware6DefaultFolderException;
use Ergonode\ExporterShopware6\Infrastructure\Exception\Shopware6InstanceOfException;
use Ergonode\ExporterShopware6\Infrastructure\Model\Shopware6Category;
use Ergonode\ExporterShopware6\Infrastructure\Model\Shopware6Media;
use Ergonode\ExporterShopware6\Infrastructure\Model\Shopware6MediaDefaultFolder;
use Ergonode\Multimedia\Domain\Entity\Multimedia;
use Ergonode\SharedKernel\Domain\Aggregate\ChannelId;
use Ergonode\SharedKernel\Domain\Aggregate\MultimediaId;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use League\Flysystem\FilesystemInterface;

class Shopware6CategoryMediaClient
{
    private const CACHE_KEY_CATEGORY_FOLDER_ID = 'category-folder-id';
    private const CACHE_KEY_HAS_MEDIA         = 'has-media';

    private Shopware6Connector $connector;

    private FilesystemInterface $multimediaStorage;

    private MultimediaRepositoryInterface $multimediaRepository;

    private array $inMemoryCache = [];

    public function __construct(
        Shopware6Connector $connector,
        FilesystemInterface $multimediaStorage,
        MultimediaRepositoryInterface $multimediaRepository
    ) {
        $this->connector = $connector;
        $this->multimediaStorage = $multimediaStorage;
        $this->multimediaRepository = $multimediaRepository;
    }

    /**
     * @throws Shopware6DefaultFolderException
     * @throws Exception
     */
    public function findOrCreateMedia(
        Shopware6Channel $channel,
        Multimedia $multimedia,
        Shopware6Category $shopware6Category
    ): string {
        $shopwareId = $this->check($channel, $multimedia, $shopware6Category);
        if ($shopwareId) {
            return $shopwareId;
        }

        $folder = $this->getCategoryFolderId($channel);
        if (null === $folder) {
            throw new Shopware6DefaultFolderException();
        }

        $shopwareId = $this->findByFilename($channel, $multimedia);
        if ($shopwareId) {
            return $shopwareId;
        }

        return $this->createNew($channel, $multimedia, $folder)->getId();
    }

    /**
     * @throws Exception
     */
    private function createNew(
        Shopware6Channel $channel,
        Multimedia $multimedia,
        Shopware6MediaDefaultFolder $folder
    ): Shopware6Media {
        $media = null;
        try {
            $media = $this->createMediaResource($channel, $folder);
            $this->upload($channel, $media, $multimedia);
            $this->multimediaRepository->save($channel->getId(), $multimedia->getId(), $media->getId());

            return $media;
        } catch (Exception $exception) {
            if ($media) {
                $this->delete($channel, $media->getId(), $multimedia->getId());
            }
            throw $exception;
        }
    }

    private function upload(Shopware6Channel $channel, Shopware6Media $media, Multimedia $multimedia): void
    {
        $content = $this->multimediaStorage->read($multimedia->getFileName());
        $name = $multimedia->getFileName();
        try {
            $action = new PostUploadFile($media->getId(), $content, $multimedia, $name);
            $this->connector->execute($channel, $action);

            return;
        } catch (ServerException $exception) {
            $response = $exception->getResponse();

            if (null !== $response) {
                $decode = json_decode(
                    $response->getBody()->getContents(),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                if ($decode['errors'][0]['code'] !== 'CONTENT__MEDIA_DUPLICATED_FILE_NAME') {
                    throw $exception;
                }
            }
        }
    }

    /**
     * @throws Shopware6InstanceOfException|GuzzleException
     */
    private function createMediaResource(
        Shopware6Channel $channel,
        Shopware6MediaDefaultFolder $folder
    ): Shopware6Media {
        $action = new PostCreateMediaAction($folder->getMediaFolderId(), true);

        $shopware6Media = $this->connector->execute($channel, $action);
        if (!$shopware6Media instanceof Shopware6Media) {
            throw new Shopware6InstanceOfException(Shopware6Media::class);
        }

        return $shopware6Media;
    }

    private function getCategoryFolderId(Shopware6Channel $channel): ?Shopware6MediaDefaultFolder
    {
        $hasInCache = $this->getFromCacheCategoryFolderId($channel->getId());
        if (null !== $hasInCache) {
            return $hasInCache;
        }

        $query = new Shopware6QueryBuilder();
        $query->equals('entity', 'category');

        $action = new GetMediaDefaultFolderList($query);

        $categoryFolderId = null;
        $folderList = $this->connector->execute($channel, $action);
        if (is_array($folderList) && count($folderList) > 0) {
            $categoryFolderId = reset($folderList);
            $this->setInCacheCategoryFolderId($channel->getId(), $categoryFolderId);
        }

        return $categoryFolderId;
    }

    private function setInCacheCategoryFolderId(ChannelId $channelId, Shopware6MediaDefaultFolder $productFolderId): void
    {
        $this->inMemoryCache[self::CACHE_KEY_CATEGORY_FOLDER_ID][$channelId->getValue()] = $productFolderId;
    }

    private function getFromCacheCategoryFolderId(ChannelId $channelId): ?Shopware6MediaDefaultFolder
    {
        return $this->inMemoryCache[self::CACHE_KEY_CATEGORY_FOLDER_ID][$channelId->getValue()] ?? null;
    }

    private function setInCacheHasMedia(ChannelId $channelId, string $shopwareId): void
    {
        $this->inMemoryCache[self::CACHE_KEY_HAS_MEDIA][$channelId->getValue()][$shopwareId] = true;
    }

    private function getFromCacheHasMedia(ChannelId $channelId, string $shopwareId): bool
    {
        return isset($this->inMemoryCache[self::CACHE_KEY_HAS_MEDIA][$channelId->getValue()][$shopwareId]);
    }

    private function removeFromCacheHasMedia(ChannelId $channelId, string $shopwareId): void
    {
        unset($this->inMemoryCache[self::CACHE_KEY_HAS_MEDIA][$channelId->getValue()][$shopwareId]);
    }

    private function check(
        Shopware6Channel $channel,
        Multimedia $multimedia,
        Shopware6Category $shopware6Category
    ): ?string {
        if (!$this->multimediaRepository->exists($channel->getId(), $multimedia->getId())) {
            return null;
        }
        $shopwareId = $this->multimediaRepository->load($channel->getId(), $multimedia->getId());

        $hasMediaInShopware = $this->hasMedia($channel, $shopwareId);
        if(!$hasMediaInShopware) {
            return null;
        }

        return $shopwareId;
    }


    /**
     * @throws Shopware6InstanceOfException|GuzzleException
     */
    private function hasMedia(Shopware6Channel $channel, string $shopwareId): bool
    {
        $action = new HasMedia($shopwareId);

        $hasMedia = $this->getFromCacheHasMedia($channel->getId(), $shopwareId);
        if ($hasMedia) {
            return true;
        }

        try {
            $shopware6MediaId = $this->connector->execute($channel, $action);
            if (!is_string($shopware6MediaId)) {
                throw new Shopware6InstanceOfException(Shopware6Media::class);
            }

            $hasMedia = true;
            $this->setInCacheHasMedia($channel->getId(), $shopwareId);
        } catch (ClientException $exception) {
        }

        return $hasMedia;
    }

    private function delete(Shopware6Channel $channel, string $shopwareId, MultimediaId $multimediaId): void
    {
        try {
            $action = new DeleteMedia($shopwareId);
            $this->connector->execute($channel, $action);
        } catch (ClientException $exception) {
        }
        $this->multimediaRepository->delete($channel->getId(), $multimediaId);
        $this->removeFromCacheHasMedia($channel->getId(), $shopwareId);
    }

    private function getMediaByFilename(Shopware6Channel $channel, string $filename): ?string
    {
        $query = new Shopware6QueryBuilder();
        $query->equals('fileName', $filename)
              ->limit(1);

        $action = new GetMediaByFilename($query);

        try {
            $shopware6MediaId = $this->connector->execute($channel, $action);
            if (is_string($shopware6MediaId)) {
                return $shopware6MediaId;
            }
        } catch (ClientException $exception) {
        }

        return null;
    }

    private function findByFilename(Shopware6Channel $channel, Multimedia $multimedia): ?string
    {
        $name = str_replace(sprintf('.%s', $multimedia->getExtension()), "", $multimedia->getName());
        $shopwareId = $this->getMediaByFilename($channel, $name);
        if ($shopwareId) {
            $this->multimediaRepository->save($channel->getId(), $multimedia->getId(), $shopwareId);

            return $shopwareId;
        }

        return null;
    }
}
