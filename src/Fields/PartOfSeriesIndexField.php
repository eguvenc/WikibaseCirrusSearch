<?php

declare(strict_types=1);

namespace Wikibase\Search\Elastic\Fields;

use Wikibase\DataModel\Entity\EntityDocument;

final class PartOfSeriesIndexField extends AbstractEventLinkedEntityIndexField
{
    public const NAME = 'part_of_series';

    public function __construct()
    {
        parent::__construct(self::NAME);
    }

    public function getFieldData(EntityDocument $entity)
    {
        $eventPropertyId = $this->getEventPropertyId('part_of_series');

        $entities = $this->getLinkedEntitiesByProperty(
            $entity,
            $eventPropertyId
        );

        if ($entities === []) {
            return null;
        }

        // The configured field uses a single-object structure.
        $series = reset($entities);

        if (!$series instanceof EntityDocument) {
            return null;
        }

        return [
            'id' => $this->getEntityId($series),
            'uuid' => $this->getConfiguredEntityPropertyValue(
                $series,
                'uuid'
            ),
            'name' => $this->getEntityLabel($series),
        ];
    }

    protected function getFieldMapping(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'keyword'],
                'uuid' => ['type' => 'keyword'],
                'name' => $this->getTextMapping(),
            ],
        ];
    }
}