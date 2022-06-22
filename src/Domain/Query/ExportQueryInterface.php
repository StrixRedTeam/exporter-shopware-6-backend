<?php
declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Domain\Query;

use Ergonode\SharedKernel\Domain\Aggregate\ChannelId;

interface ExportQueryInterface
{
    public function findLastExportStarted(ChannelId $channelId): ?\DateTime;

    public function isLastExportFinished(ChannelId $channelId): bool;
}
