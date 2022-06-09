<?php
declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Domain\Command\Export;

use Ergonode\Channel\Domain\Command\ExporterCommandInterface;
use Ergonode\SharedKernel\Domain\Aggregate\AttributeId;
use Ergonode\SharedKernel\Domain\Aggregate\ExportId;
use Webmozart\Assert\Assert;

abstract class AbstractBatchExportCommand implements ExporterCommandInterface
{
    private ExportId $exportId;

    /**
     * @var AttributeId[]
     */
    private array $attributeIds;

    /**
     * @var string[]
     */
    private array $entities;

    public function __construct(ExportId $exportId, array $attributeIds, array $entities = ['product'])
    {
        $this->exportId = $exportId;
        Assert::allIsInstanceOf($attributeIds, AttributeId::class);
        $this->attributeIds = $attributeIds;
        $this->entities = $entities;
    }

    public function getExportId(): ExportId
    {
        return $this->exportId;
    }

    public function getAttributeIds(): array
    {
        return $this->attributeIds;
    }

    public function getEntities(): array
    {
        return $this->entities;
    }
}
