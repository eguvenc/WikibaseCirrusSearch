<?php

declare(strict_types=1);

namespace Wikibase\Search\Elastic\Fields;

use Confident\Helpers\CommonPropertiesConfig;
use Confident\Helpers\OrganizationPropertiesConfig;
use Wikibase\DataModel\Entity\EntityDocument;

final class OrganizationSuggestionIndexField
    extends AbstractEventLinkedEntityIndexField
{
    public const NAME = 'organization_suggestion';

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

        $uuid = $this->readScalar($entity, $uuidPropertyId);
        $rorId = $this->readScalar($entity, $rorPropertyId);
        $sourceUrl = $this->readScalar($entity, $sourceUrlPropertyId);

        $parentQid = null;
        $parentUuid = null;
        $parentName = null;

        if ($this->isValidPropertyId($partOfPropertyId)) {
            $parents = $this->getLinkedEntitiesByProperty(
                $entity,
                $partOfPropertyId
            );

            if ($parents !== []) {
                $parent = $parents[0];
                $parentId = $this->getEntityId($parent);

                $parentQid = $parentId !== '' ? $parentId : null;
                $parentName = $this->getEntityLabel($parent);

                $parentUuidPropertyId = $common['uuid']
                    ?? $organization['uuid']
                    ?? null;

                if ($this->isValidPropertyId($parentUuidPropertyId)) {
                    $value = trim(
                        $this->getEntityPropertyValue(
                            $parent,
                            $parentUuidPropertyId
                        )
                    );

                    $parentUuid = $value !== '' ? $value : null;
                }
            }
        }

        if (
            $uuid === null
            && $rorId === null
            && $sourceUrl === null
            && $parentQid === null
            && $parentUuid === null
            && $parentName === null
        ) {
            return [];
        }

        return [
            'uuid' => $uuid,
            'ror_id' => $rorId,
            'source_url' => $sourceUrl,
            'parent_qid' => $parentQid,
            'parent_uuid' => $parentUuid,
            'parent_name' => $parentName,
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

    protected function getFieldMapping(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'uuid' => ['type' => 'keyword'],
                'ror_id' => ['type' => 'keyword'],
                'source_url' => ['type' => 'keyword'],
                'parent_qid' => ['type' => 'keyword'],
                'parent_uuid' => ['type' => 'keyword'],
                'parent_name' => $this->getTextMapping(),
            ],
        ];
    }
}