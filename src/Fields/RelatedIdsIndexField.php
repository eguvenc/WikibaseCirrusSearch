<?php

declare(strict_types=1);

namespace Wikibase\Search\Elastic\Fields;

use Wikibase\DataModel\Entity\EntityDocument;

final class RelatedIdsIndexField extends AbstractEventStatementsIndexField
{
    public const NAME = 'related_ids';

    public function __construct() { parent::__construct(self::NAME); }

    public function getFieldData(EntityDocument $entity)
    {
        $rows = [];
        foreach ($this->getStatementsForProperty($entity, 'related_id') as $statement) {
            $idValue = $this->getMainValue($statement);
            if (!is_string($idValue) && !is_numeric($idValue)) {
                continue;
            }
            $relatedTo = $this->getQualifierEntityFields($statement, 'related_to');
            $idType = $this->getQualifierEntityFields($statement, 'related_id_type');
            $resourceType = $this->getQualifierEntityFields($statement, 'related_resource_type');
            $relationType = $this->getQualifierEntityFields($statement, 'related_relation_type');

            $rows[] = [
                'id' => (string)$idValue,
                'related_to' => $relatedTo['label'],
                'related_to_id' => $relatedTo['id'],
                'related_id_type' => $idType['label'],
                'related_id_type_id' => $idType['id'],
                'related_resource_type' => $resourceType['label'],
                'related_resource_type_id' => $resourceType['id'],
                'related_relation_type' => $relationType['label'],
                'related_relation_type_id' => $relationType['id'],
            ];
        }
        return $rows;
    }

    protected function getFieldMapping(): array
    {
        return $this->getNestedMapping([
            'id' => $this->getKeywordMapping(),
            'related_to' => $this->getTextMapping(),
            'related_to_id' => $this->getKeywordMapping(),
            'related_id_type' => $this->getTextMapping(),
            'related_id_type_id' => $this->getKeywordMapping(),
            'related_resource_type' => $this->getTextMapping(),
            'related_resource_type_id' => $this->getKeywordMapping(),
            'related_relation_type' => $this->getTextMapping(),
            'related_relation_type_id' => $this->getKeywordMapping(),
        ]);
    }
}