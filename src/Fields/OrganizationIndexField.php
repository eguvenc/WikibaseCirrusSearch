<?php

declare(strict_types=1);

namespace Wikibase\Search\Elastic\Fields;

use Confident\Helpers\CommonPropertiesConfig;
use Confident\Helpers\OrganizationPropertiesConfig;
use Wikibase\DataModel\Entity\EntityDocument;

final class OrganizationIndexField extends AbstractEventLinkedEntityIndexField
{
    public const NAME = 'organization';

    public function __construct()
    {
        parent::__construct(self::NAME);
    }

    public function getFieldData(EntityDocument $entity): array
    {
        $organization = OrganizationPropertiesConfig::get();
        $common = CommonPropertiesConfig::get();

        $uuidPropertyId = $organization['uuid']
            ?? $common['uuid']
            ?? null;

        $rorPropertyId = $organization['ror_id'] ?? null;
        $sourceUrlPropertyId = $organization['source_url'] ?? null;

        $partOfPropertyId = $common['part_of'] ?? null;
        $subclassOfPropertyId = $common['subclass_of'] ?? null;
        $instanceOfPropertyId = $common['instance_of'] ?? null;

        $uuid = $this->readScalar($entity, $uuidPropertyId);
        $rorId = $this->readScalar($entity, $rorPropertyId);
        $sourceUrl = $this->readScalar($entity, $sourceUrlPropertyId);

        $partOf = $this->readLinkedEntities(
            $entity,
            $partOfPropertyId
        );

        $subclassOf = $this->readLinkedEntities(
            $entity,
            $subclassOfPropertyId
        );

        $instanceOf = $this->readLinkedEntities(
            $entity,
            $instanceOfPropertyId
        );

        if (
            $uuid === null
            && $rorId === null
            && $sourceUrl === null
            && $partOf === []
            && $subclassOf === []
            && $instanceOf === []
        ) {
            return [];
        }

        return [
            'uuid' => $uuid,
            'ror_id' => $rorId,
            'source_url' => $sourceUrl,
            'part_of' => $partOf,
            'subclass_of' => $subclassOf,
            'instance_of' => $instanceOf,
        ];
    }

    private function readScalar(
        EntityDocument $entity,
        ?string $propertyId
    ): ?string {
        if (!$this->isValidPropertyId($propertyId)) {
            return null;
        }

        $value = trim(
            $this->getEntityPropertyValue($entity, $propertyId)
        );

        return $value !== '' ? $value : null;
    }

    private function readLinkedEntities(
        EntityDocument $entity,
        ?string $propertyId
    ): array {
        if (!$this->isValidPropertyId($propertyId)) {
            return [];
        }

        $linkedEntities = $this->getLinkedEntitiesByProperty(
            $entity,
            $propertyId
        );

        $result = [];

        foreach ($linkedEntities as $linkedEntity) {
            $id = $this->getEntityId($linkedEntity);

            if ($id === '') {
                continue;
            }

            $item = [
                'id' => $id,
                'name' => $this->getEntityLabel($linkedEntity),
            ];

            $uuidPropertyId = CommonPropertiesConfig::get()['uuid']
                ?? OrganizationPropertiesConfig::get()['uuid']
                ?? null;

            if ($this->isValidPropertyId($uuidPropertyId)) {
                $uuid = trim(
                    $this->getEntityPropertyValue(
                        $linkedEntity,
                        $uuidPropertyId
                    )
                );

                if ($uuid !== '') {
                    $item['uuid'] = $uuid;
                }
            }

            $result[] = $item;
        }

        return $result;
    }

    protected function getFieldMapping(): array
    {
        $linkedEntityProperties = [
            'id' => ['type' => 'keyword'],
            'uuid' => ['type' => 'keyword'],
            'name' => $this->getTextMapping(),
        ];

        return [
            'type' => 'object',
            'properties' => [
                'uuid' => ['type' => 'keyword'],
                'ror_id' => ['type' => 'keyword'],
                'source_url' => ['type' => 'keyword'],

                'part_of' => [
                    'type' => 'nested',
                    'properties' => $linkedEntityProperties,
                ],

                'subclass_of' => [
                    'type' => 'nested',
                    'properties' => $linkedEntityProperties,
                ],

                'instance_of' => [
                    'type' => 'nested',
                    'properties' => $linkedEntityProperties,
                ],
            ],
        ];
    }
}