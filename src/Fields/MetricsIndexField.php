<?php

declare(strict_types=1);

namespace Wikibase\Search\Elastic\Fields;

use Wikibase\DataModel\Entity\EntityDocument;

final class MetricsIndexField extends AbstractEventStatementsIndexField
{
    public const NAME = 'metrics';

    public function __construct() { parent::__construct(self::NAME); }

    public function getFieldData(EntityDocument $entity)
    {
        $rows = [];
        foreach ($this->getStatementsForProperty($entity, 'metric_value') as $statement) {
            $value = $this->getMainValue($statement);
            if (!is_string($value) && !is_numeric($value)) {
                continue;
            }
            $metricType = $this->getQualifierEntityFields($statement, 'metric_type');
            $submissionType = $this->getQualifierEntityFields($statement, 'submission_type');
            $unitType = $this->getQualifierEntityFields($statement, 'unit_type');

            $rows[] = [
                'metric_value' => (string)$value,
                'metric_type' => $metricType['label'],
                'metric_type_id' => $metricType['id'],
                'submission_type' => $submissionType['label'],
                'submission_type_id' => $submissionType['id'],
                'unit_type' => $unitType['label'],
                'unit_type_id' => $unitType['id'],
            ];
        }
        return $rows;
    }

    protected function getFieldMapping(): array
    {
        return $this->getNestedMapping([
            'metric_value' => $this->getKeywordMapping(),
            'metric_type' => $this->getTextMapping(),
            'metric_type_id' => $this->getKeywordMapping(),
            'submission_type' => $this->getTextMapping(),
            'submission_type_id' => $this->getKeywordMapping(),
            'unit_type' => $this->getTextMapping(),
            'unit_type_id' => $this->getKeywordMapping(),
        ]);
    }
}