<?php

declare(strict_types=1);

namespace Wikibase\Search\Elastic\Fields;

use Wikibase\DataModel\Entity\EntityDocument;

final class RelatedExternalEventsIndexField extends AbstractEventStatementsIndexField
{
    public const NAME = 'related_external_events';

    public function __construct() { parent::__construct(self::NAME); }

    public function getFieldData(EntityDocument $entity)
    {
        $rows = [];
        foreach ($this->getStatementsForProperty($entity, 'external_event_url') as $statement) {
            $url = $this->getMainValue($statement);
            if (!is_string($url) || trim($url) === '') {
                continue;
            }
            $idType = $this->getQualifierEntityFields($statement, 'related_event_id_type');
            $relationType = $this->getQualifierEntityFields($statement, 'related_event_relation_type');
            $rows[] = [
                'url' => trim($url),
                'related_event_id_type' => $idType['label'],
                'related_event_id_type_id' => $idType['id'],
                'related_relation_type' => $relationType['label'],
                'related_relation_type_id' => $relationType['id'],
            ];
        }
        return $rows;
    }

    protected function getFieldMapping(): array
    {
        return $this->getNestedMapping([
            'url' => $this->getKeywordMapping(),
            'related_event_id_type' => $this->getTextMapping(),
            'related_event_id_type_id' => $this->getKeywordMapping(),
            'related_relation_type' => $this->getTextMapping(),
            'related_relation_type_id' => $this->getKeywordMapping(),
        ]);
    }
}