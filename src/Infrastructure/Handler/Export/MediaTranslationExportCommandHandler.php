<?php

declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Infrastructure\Handler\Export;

use Ergonode\Channel\Domain\Repository\ExportRepositoryInterface;
use Ergonode\ExporterShopware6\Domain\Command\Export\MediaTranslationExportCommand;
use Ergonode\ExporterShopware6\Infrastructure\Model\Media\MediaCachePool;
use Ergonode\ExporterShopware6\Infrastructure\Processor\Process\MediaTranslationShopware6ExportProcess;
use Ergonode\Multimedia\Domain\Entity\AbstractMultimedia;
use Ergonode\Multimedia\Domain\Repository\MultimediaRepositoryInterface;
use Ergonode\SharedKernel\Domain\Aggregate\MultimediaId;
use Webmozart\Assert\Assert;
use Ergonode\Channel\Domain\Entity\Export;
use Ergonode\Channel\Domain\Repository\ChannelRepositoryInterface;
use Ergonode\ExporterShopware6\Domain\Entity\Shopware6Channel;

class MediaTranslationExportCommandHandler
{
    private ExportRepositoryInterface $exportRepository;

    private ChannelRepositoryInterface $channelRepository;

    private MediaTranslationShopware6ExportProcess $process;

    private MultimediaRepositoryInterface $multimediaRepository;

    private MediaCachePool $mediaCachePool;

    public function __construct(
        MediaCachePool $mediaCachePool,
        ExportRepositoryInterface $exportRepository,
        ChannelRepositoryInterface $channelRepository,
        MultimediaRepositoryInterface $multimediaRepository,
        MediaTranslationShopware6ExportProcess $process
    ) {
        $this->mediaCachePool = $mediaCachePool;
        $this->exportRepository = $exportRepository;
        $this->channelRepository = $channelRepository;
        $this->process = $process;
        $this->multimediaRepository = $multimediaRepository;
    }

    public function __invoke(MediaTranslationExportCommand $command): void
    {
        $export  = $this->exportRepository->load($command->getExportId());
        Assert::isInstanceOf($export, Export::class);
        /** @var Shopware6Channel $channel */
        $channel = $this->channelRepository->load($export->getChannelId());
        Assert::isInstanceOf($channel, Shopware6Channel::class);

        if ($this->mediaCachePool->isAvailable()) {
            foreach ($this->mediaCachePool->getData() as $multimediaId => $shopwareId) {
                $multimedia = $this->multimediaRepository->load(new MultimediaId($multimediaId));
                Assert::isInstanceOf($multimedia, AbstractMultimedia::class);

                $this->process->process($export, $channel, $multimedia, (string)$shopwareId);
            }
        }
        $this->mediaCachePool->deleteCache();
    }
}
