<?php
/**
 * Copyright © Ergonode Sp. z o.o. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Infrastructure\Handler\Export;

use Ergonode\Channel\Domain\Entity\Export;
use Ergonode\Channel\Domain\Repository\ChannelRepositoryInterface;
use Ergonode\Channel\Domain\Repository\ExportRepositoryInterface;
use Ergonode\ExporterShopware6\Domain\Command\Export\BatchCustomFieldExportCommand;
use Ergonode\ExporterShopware6\Domain\Entity\Shopware6Channel;
use Ergonode\ExporterShopware6\Infrastructure\Processor\Process\CustomFieldShopware6ExportProcess;

class BatchCustomFieldExportCommandHandler extends AbstractBatchExportCommandHandler
{
    private CustomFieldShopware6ExportProcess $process;

    public function __construct(
        ExportRepositoryInterface $exportRepository,
        ChannelRepositoryInterface $channelRepository,
        CustomFieldShopware6ExportProcess $process
    ) {
        parent::__construct($exportRepository, $channelRepository);
        $this->process = $process;
    }

    public function __invoke(BatchCustomFieldExportCommand $command): void
    {
        $this->validateAndProcessCommand($command);
    }

    protected function processCommand(Export $export, Shopware6Channel $channel, array $attributeIds, array $entites): void
    {
        $this->process->process($export, $channel, $attributeIds, $entites);
    }
}
