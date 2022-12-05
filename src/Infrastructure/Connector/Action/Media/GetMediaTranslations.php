<?php

declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Infrastructure\Connector\Action\Media;

use Ergonode\ExporterShopware6\Infrastructure\Connector\AbstractAction;
use Ergonode\ExporterShopware6\Infrastructure\Connector\Shopware6QueryBuilder;
use Ergonode\ExporterShopware6\Infrastructure\Model\Media\Shopware6MediaTranslation;
use GuzzleHttp\Psr7\Request;
use Symfony\Component\HttpFoundation\Request as HttpRequest;

class GetMediaTranslations extends AbstractAction
{
    private const URI = '/api/media/%s/translations?%s';

    private string $mediaId;

    private Shopware6QueryBuilder $query;

    public function __construct(Shopware6QueryBuilder $query, string $mediaId)
    {
        $this->query = $query;
        $this->mediaId = $mediaId;
    }

    public function getRequest(): Request
    {
        return new Request(
            HttpRequest::METHOD_GET,
            $this->getUri(),
            $this->buildHeaders()
        );
    }

    /**
     * @return Shopware6MediaTranslation[]
     * @throws \JsonException
     */
    public function parseContent(?string $content): array
    {
        if (null === $content) {
            return [];
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (empty($data['data'])) {
            return [];
        }

        $translations = [];
        foreach ($data['data'] as $translation) {
            if (empty($translation['id']) || empty($translation['attributes']['languageId'])) {
                continue;
            }
            $translations[$translation['attributes']['languageId']] = new Shopware6MediaTranslation(
                $translation['id'],
                $translation['attributes']['alt'] ?? null,
                $translation['attributes']['title'] ?? null,
                $translation['attributes']['languageId']
            );
        }

        return $translations;
    }

    private function getUri(): string
    {
        return rtrim(sprintf(self::URI, $this->mediaId, $this->query->getQuery()), '?');
    }
}
