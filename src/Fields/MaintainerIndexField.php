<?php

declare(strict_types=1);

namespace Wikibase\Search\Elastic\Fields;

use Wikibase\DataModel\Entity\EntityDocument;

final class MaintainerIndexField extends AbstractEventStatementsIndexField
{
    public const NAME = 'maintainer';

    public function __construct()
    {
        parent::__construct(self::NAME);
    }

    public function getFieldData(EntityDocument $entity): array
    {
        $eventPropertyId = $this->getConfiguredEventPropertyId('maintainer');
        if ($eventPropertyId === null) {
            return [];
        }

        $maintainerEntities = $this->getLinkedEntitiesByProperty(
            $entity,
            $eventPropertyId
        );

        // The sub-unit belongs to the maintainer statement as a qualifier.
        // Map it by the main statement's maintainer QID so each indexed
        // maintainer gets the qualifier from its own statement.
        $subUnitsByMaintainerQid = [];
        foreach ($this->getStatementsForProperty($entity, 'maintainer') as $statement) {
            $maintainerQid = $this->getMainValue($statement);
            if (!is_string($maintainerQid) || $maintainerQid === '') {
                continue;
            }

            $subUnit = $this->getQualifierEntityFields(
                $statement,
                'maintainer_sub_unit'
            );

            // Prefer a statement that actually has a sub-unit qualifier if
            // duplicate maintainer statements are encountered.
            if (
                !isset($subUnitsByMaintainerQid[$maintainerQid])
                || (
                    empty($subUnitsByMaintainerQid[$maintainerQid]['id'])
                    && !empty($subUnit['id'])
                )
            ) {
                $subUnitsByMaintainerQid[$maintainerQid] = $subUnit;
            }
        }

        $maintainers = [];
        foreach ($maintainerEntities as $maintainerEntity) {
            $maintainerQid = $this->getEntityId($maintainerEntity);
            $subUnit = $subUnitsByMaintainerQid[$maintainerQid] ?? [
                'label' => null,
                'id' => null,
            ];

            $maintainers[] = [
                'qid' => $maintainerQid,
                'uuid' => $this->getConfiguredEntityPropertyValue(
                    $maintainerEntity,
                    'uuid'
                ),
                'ror_id' => $this->normalizeRorId(
                    $this->getConfiguredEntityPropertyValue(
                        $maintainerEntity,
                        'ror_id'
                    )
                ),
                'name' => $this->getEntityLabel($maintainerEntity),
                'sub_unit_free' => $subUnit['label'],
                'sub_unit_qid' => $subUnit['id'],
                'source_url' => $this->getConfiguredEntityPropertyValue(
                    $maintainerEntity,
                    'source_url'
                ),
            ];
        }

        return $maintainers;
    }

    private function normalizeRorId(string $rorId): string
    {
        $rorId = trim($rorId);

        if (
            preg_match(
                '~(?:https?://ror\.org/)?([0-9a-z]{9})/?$~i',
                $rorId,
                $matches
            ) === 1
        ) {
            return $matches[1];
        }

        return $rorId;
    }

    protected function getFieldMapping(): array
    {
        return [
            'type' => 'nested',
            'properties' => [
                'qid' => ['type' => 'keyword'],
                'uuid' => ['type' => 'keyword'],
                'ror_id' => ['type' => 'keyword'],
                'name' => $this->getTextMapping(),
                'sub_unit_free' => $this->getTextMapping(),
                'sub_unit_qid' => ['type' => 'keyword'],
                'source_url' => ['type' => 'keyword'],
            ],
        ];
    }
}