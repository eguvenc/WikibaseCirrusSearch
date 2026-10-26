<?php

declare(strict_types=1);

namespace Wikibase\Search\Elastic\Fields;

use Wikibase\DataModel\Entity\EntityDocument;

final class DeadlinesIndexField extends AbstractEventStatementsIndexField
{
    public const NAME = 'deadlines';

    public function __construct() { parent::__construct(self::NAME); }

    public function getFieldData(EntityDocument $entity)
    {
        $rows = [];
        foreach ($this->getStatementsForProperty($entity, 'deadline_date') as $statement) {
            $date = $this->getMainValue($statement);
            if (!is_string($date) || trim($date) === '') {
                continue;
            }
            $category = $this->getQualifierEntityFields($statement, 'deadline_category');
            $type = $this->getQualifierEntityFields($statement, 'deadline_type');
            $rows[] = [
                'deadline_category' => $category['label'],
                'deadline_category_id' => $category['id'],
                'deadline_type' => $type['label'],
                'deadline_type_id' => $type['id'],
                'deadline_date' => $date,
            ];
        }
        return $rows;
    }

    protected function getFieldMapping(): array
    {
        return $this->getNestedMapping([
            'deadline_category' => $this->getTextMapping(),
            'deadline_category_id' => $this->getKeywordMapping(),
            'deadline_type' => $this->getTextMapping(),
            'deadline_type_id' => $this->getKeywordMapping(),
            'deadline_date' => ['type' => 'date'],
        ]);
    }
}