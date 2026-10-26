<?php

declare(strict_types=1);

namespace Wikibase\Search\Elastic\Fields;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\ItemId;

final class RelatedEventsIndexField extends AbstractEventStatementsIndexField
{
    public const NAME = 'related_events';

    public function __construct() { parent::__construct(self::NAME); }

    public function getFieldData(EntityDocument $entity)
    {
        $rows = [];
        foreach ($this->getStatementsForProperty($entity, 'related_event_id') as $statement) {
            $id = $this->getMainValue($statement);
            if (!is_string($id) || preg_match('/^Q[1-9][0-9]*$/', $id) !== 1) {
                continue;
            }

            $idType = $this->getQualifierEntityFields($statement, 'related_event_id_type');
            $relationType = $this->getQualifierEntityFields($statement, 'related_event_relation_type');
            $relatedEntity = null;
            try {
                $relatedEntity = $this->loadItemEntity(new ItemId($id));
            } catch (\Throwable $e) {
                $relatedEntity = null;
            }

            $rows[] = [
                'related_event_id' => $id,
                'related_event_name' => $relatedEntity !== null ? $this->getEntityLabel($relatedEntity) : null,
                'related_event_id_type' => $idType['label'],
                'related_event_id_type_id' => $idType['id'],
                'related_relation_type' => $relationType['label'],
                'related_relation_type_id' => $relationType['id'],
                'related_event_uuid' => $relatedEntity !== null
                    ? ($this->getConfiguredEntityPropertyValue($relatedEntity, 'uuid') ?: null)
                    : null,
            ];
        }
        return $rows;
    }

    protected function getFieldMapping(): array
    {
        return $this->getNestedMapping([
            'related_event_id' => $this->getKeywordMapping(),
            'related_event_name' => $this->getTextMapping(),
            'related_event_id_type' => $this->getTextMapping(),
            'related_event_id_type_id' => $this->getKeywordMapping(),
            'related_relation_type' => $this->getTextMapping(),
            'related_relation_type_id' => $this->getKeywordMapping(),
            'related_event_uuid' => $this->getKeywordMapping(),
        ]);
    }
}