<?php

declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Infrastructure\Processor\Process;

use Ergonode\Channel\Domain\Entity\Export;
use Ergonode\Channel\Domain\Repository\ExportRepositoryInterface;
use Ergonode\ExporterShopware6\Domain\Entity\Shopware6Channel;
use Ergonode\ExporterShopware6\Domain\Repository\LanguageRepositoryInterface;
use Ergonode\ExporterShopware6\Domain\Repository\ProductRepositoryInterface;
use Ergonode\ExporterShopware6\Infrastructure\Connector\Action\Media\GetMediaTranslations;
use Ergonode\ExporterShopware6\Infrastructure\Connector\Action\Media\PatchMediaAction;
use Ergonode\ExporterShopware6\Infrastructure\Connector\Shopware6Connector;
use Ergonode\ExporterShopware6\Infrastructure\Connector\Shopware6QueryBuilder;
use Ergonode\ExporterShopware6\Infrastructure\Model\Media\Shopware6MediaTranslation;
use Ergonode\ExporterShopware6\Infrastructure\Model\Shopware6Media;
use Ergonode\Multimedia\Domain\Entity\AbstractMultimedia;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Throwable;
use Webmozart\Assert\Assert;

class MediaTranslationShopware6ExportProcess
{
    private Shopware6Connector $connector;

    protected ProductRepositoryInterface $shopwareProductRepository;

    private LanguageRepositoryInterface $languageRepository;

    private ExportRepositoryInterface $exportRepository;

    private LoggerInterface $logger;

    public function __construct(
        Shopware6Connector $connector,
        LanguageRepositoryInterface $languageRepository,
        ExportRepositoryInterface $exportRepository,
        ProductRepositoryInterface $shopwareProductRepository,
        LoggerInterface $logger
    ) {
        $this->connector = $connector;
        $this->languageRepository = $languageRepository;
        $this->exportRepository = $exportRepository;
        $this->shopwareProductRepository = $shopwareProductRepository;
        $this->logger = $logger;
    }

    public function process(
        Export $export,
        Shopware6Channel $channel,
        AbstractMultimedia $multimedia,
        string $shopwareId
    ): void {
        try {
            $this->ensureMediaTranslations($channel, $multimedia, $shopwareId);
        } catch (Throwable $exception) {
            $this->exportRepository->addError($export->getId(), $exception->getMessage(), []);
        }
    }

    /**
     * @throws GuzzleException
     */
    private function ensureMediaTranslations(
        Shopware6Channel $channel,
        AbstractMultimedia $multimedia,
        string $shopwareId
    ): void {
        //apply translations from shopware
        $shopware6Media = new Shopware6Media($shopwareId, null);
        $shopware6Media->setTranslations($this->getMediaTranslations($channel, $shopwareId));
        //apply translations from ergo
        $altTranslations = $multimedia->getAlt()->getTranslations();
        $titleTranslations = $multimedia->getTitle()->getTranslations();
        $languages = array_unique(array_merge(array_keys($altTranslations), array_keys($titleTranslations)));
        $ergoTranslations = [];
        foreach ($channel->getLanguages() as $channelLanguage) {
            if (!in_array($channelLanguage->getCode(), $languages)) {
                continue;
            }
            if ($this->languageRepository->exists($channel->getId(), $channelLanguage->getCode())) {
                $shopwareLanguage = $this->languageRepository->load($channel->getId(), $channelLanguage->getCode());
                Assert::notNull(
                    $shopwareLanguage,
                    sprintf('Expected a value other than null for product lang  %s', $channelLanguage->getCode())
                );
                $ergoTranslations[] = new Shopware6MediaTranslation(
                    null,
                    $altTranslations[$shopwareLanguage->getIso()] ?? null,
                    $titleTranslations[$shopwareLanguage->getIso()] ?? null,
                    $shopwareLanguage->getId()
                );
            }
        }
        $shopware6Media->updateTranslated($ergoTranslations);

        if ($shopware6Media->isModified()) {
            $this->logger->info(
                'Updating multimedia media translations start',
                [
                    'multimediaId' => $multimedia->getId()->getValue(),
                    'multimediaName' => $multimedia->getName(),
                    'mediaId' => $shopware6Media->getId()
                ]
            );
            $this->updateTranslations($channel, $shopware6Media);
            $this->logger->info(
                'Updating multimedia media translations end',
                [
                    'multimediaId' => $multimedia->getId()->getValue(),
                    'multimediaName' => $multimedia->getName(),
                    'mediaId' => $shopware6Media->getId()
                ]
            );
        }
    }

    /**
     * @throws GuzzleException
     */
    private function updateTranslations(Shopware6Channel $channel, Shopware6Media $shopware6Media): void
    {
        if (!empty($shopware6Media->getTranslations())) {
            $action = new PatchMediaAction($shopware6Media);
            $this->connector->execute($channel, $action);
        }
    }

    /**
     * @return Shopware6MediaTranslation[]
     * @throws GuzzleException
     */
    private function getMediaTranslations(Shopware6Channel $channel, string $shopwareMediaId): array
    {
        $query = new Shopware6QueryBuilder();
        $query->limit(1000);

        $action = new GetMediaTranslations($query, $shopwareMediaId);

        try {
            return $this->connector->execute($channel, $action);
        } catch (ClientException $exception) {
        }

        return [];
    }
}
