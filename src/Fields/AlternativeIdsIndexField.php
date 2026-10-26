<?php

declare(strict_types=1);

namespace Wikibase\Search\Elastic\Fields;

use Wikibase\DataModel\Entity\EntityDocument;

final class AlternativeIdsIndexField extends AbstractEventStatementsIndexField
{
    public const NAME = 'alternative_ids';

    public function __construct() { parent::__construct(self::NAME); }

    public function getFieldData(EntityDocument $entity)
    {
        $rows = [];
        foreach ($this->getStatementsForProperty($entity, 'alternative_id') as $statement) {
            $idValue = $this->getMainValue($statement);
            if (!is_string($idValue) && !is_numeric($idValue)) {
                continue;
            }
            $idType = $this->getQualifierEntityFields($statement, 'id_type');
            $relatedTo = $this->getQualifierEntityFields($statement, 'related_to');
            $rows[] = [
                'id' => (string)$idValue,
                'id_type' => $idType['label'],
                'id_type_id' => $idType['id'],
                'related_to' => $relatedTo['label'],
                'related_to_id' => $relatedTo['id'],
            ];
        }
        return $rows;
    }

    protected function getFieldMapping(): array
    {
        return $this->getNestedMapping([
            'id' => $this->getKeywordMapping(),
            'id_type' => $this->getTextMapping(),
            'id_type_id' => $this->getKeywordMapping(),
            'related_to' => $this->getTextMapping(),
            'related_to_id' => $this->getKeywordMapping(),
        ]);
    }
}