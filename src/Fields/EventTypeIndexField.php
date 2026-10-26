<?php

declare(strict_types=1);

namespace Wikibase\Search\Elastic\Fields;

use Wikibase\DataModel\Entity\EntityDocument;

final class EventTypeIndexField extends AbstractEventStatementsIndexField
{
    public const NAME = 'event_type';

    public function __construct() { parent::__construct(self::NAME); }

    public function getFieldData(EntityDocument $entity)
    {
        $id = $this->getFirstStatementValue($entity, 'event_type');
        if (!is_string($id) || preg_match('/^Q[1-9][0-9]*$/', $id) !== 1) {
            return null;
        }

        return ['id' => $id, 'name' => $this->resolveItemLabel($id)];
    }

    protected function getFieldMapping(): array
    {
        return ['type' => 'object', 'properties' => [
            'id' => $this->getKeywordMapping(),
            'name' => $this->getTextMapping(),
        ]];
    }
}