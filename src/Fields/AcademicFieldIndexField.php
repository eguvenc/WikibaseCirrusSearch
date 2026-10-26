<?php

declare(strict_types=1);

namespace Wikibase\Search\Elastic\Fields;

use Wikibase\DataModel\Entity\EntityDocument;

final class AcademicFieldIndexField extends AbstractEventLinkedEntityIndexField
{
    public const NAME = 'academic_field';

    public function __construct()
    {
        parent::__construct(self::NAME);
    }

    public function getFieldData(EntityDocument $entity)
    {
        $eventPropertyId = $this->getEventPropertyId('academic_field');

        $academicFieldEntities = $this->getLinkedEntitiesByProperty(
            $entity,
            $eventPropertyId
        );
        $academicFields = [];
        foreach ($academicFieldEntities as $academicFieldEntity) {
            $academicFields[] = [
                'id' => $this->getEntityId($academicFieldEntity),

                'uuid' => $this->getConfiguredEntityPropertyValue(
                    $academicFieldEntity,
                    'uuid'
                ),

                'name' => $this->getEntityLabel($academicFieldEntity),
            ];
        }

        return $academicFields;
    }

    protected function getFieldMapping(): array
    {
        return [
            'type' => 'nested',
            'properties' => [
                'id' => [
                    'type' => 'keyword',
                ],
                'uuid' => [
                    'type' => 'keyword',
                ],
                'name' => $this->getTextMapping(),
            ],
        ];
    }
}