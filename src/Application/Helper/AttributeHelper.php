<?php
declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Application\Helper;

use Ergonode\Attribute\Domain\Entity\AbstractAttribute;
use Ergonode\Attribute\Domain\Entity\Attribute\AbstractOptionAttribute;
use Ergonode\Attribute\Domain\Query\OptionQueryInterface;
use Ergonode\ExporterShopware6\Domain\Query\EventStoreQueryInterface;
use Ergonode\SharedKernel\Domain\AggregateId;

class AttributeHelper
{
    private EventStoreQueryInterface $eventHistoryQuery;

    private OptionQueryInterface $optionQuery;

    public function __construct(
        EventStoreQueryInterface $eventHistoryQuery,
        OptionQueryInterface $optionQuery
    ) {
        $this->eventHistoryQuery = $eventHistoryQuery;
        $this->optionQuery = $optionQuery;
    }

    public function hasAttributeChangedSinceLastExport(AbstractAttribute $attribute, ?\DateTime $lastExportDate)
    {
        if (!$lastExportDate) {
            return false;
        }

        $lastAttributeChangeDate = $this->eventHistoryQuery->findLastDateForAggregateId($attribute->getId());
        // if custom field was not changed since last export, skip it
        if ($lastAttributeChangeDate && $lastAttributeChangeDate >= $lastExportDate) {
            return true;
        }

        if (!$attribute instanceof AbstractOptionAttribute) {
            return false;
        }

        $options = $this->optionQuery->getOptions($attribute->getId());
        foreach ($options as $option) {
            $optionId = new AggregateId($option);
            $lastOptionChangeDate = $this->eventHistoryQuery->findLastDateForAggregateId($optionId);
            if ($lastOptionChangeDate && $lastOptionChangeDate >= $lastExportDate) {
                return true;
            }
        }

        return false;
    }
}
