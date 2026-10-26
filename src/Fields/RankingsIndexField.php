<?php

declare(strict_types=1);

namespace Wikibase\Search\Elastic\Fields;

use Wikibase\DataModel\Entity\EntityDocument;

final class RankingsIndexField extends AbstractEventStatementsIndexField
{
    public const NAME = 'rankings';

    public function __construct()
    {
        parent::__construct(self::NAME);
    }

    public function getFieldData(EntityDocument $entity)
    {
        $rows = [];

        foreach ($this->getStatementsForProperty($entity, 'ranking_value') as $statement) {
            $value = $this->getMainValue($statement);
            if (!is_string($value) && !is_numeric($value)) {
                continue;
            }

            $system = $this->getQualifierEntityFields($statement, 'ranking_system');
            $quartile = $this->getQualifierEntityFields($statement, 'ranking_quartile');
            $category = $this->getQualifierValue($statement, 'ranking_category');

            $yearValue = $this->getQualifierValue($statement, 'year');
            $year = null;
            if (
                is_string($yearValue)
                && preg_match('/^[+-]?(\d{4})/', trim($yearValue), $matches) === 1
            ) {
                $year = $matches[1];
            }
            $sourceUrl = $this->getQualifierValue($statement, 'source_url');

            $rows[] = [
                'ranking_value' => (string)$value,
                'ranking_system' => $system['label'],
                'ranking_system_id' => $system['id'],
                'year' => $year,
                'source_url' => $sourceUrl,
                'ranking_category' => $category,
                'ranking_quartile' => $quartile['label'],
                'ranking_quartile_id' => $quartile['id'],
            ];
        }

        return $rows;
    }

    protected function getFieldMapping(): array
    {
        return $this->getNestedMapping([
            'ranking_value' => $this->getKeywordMapping(),
            'ranking_system' => $this->getTextMapping(),
            'ranking_system_id' => $this->getKeywordMapping(),
            'year' => $this->getKeywordMapping(),
            'source_url' => $this->getKeywordMapping(),
            'ranking_category' => $this->getTextMapping(),
            'ranking_quartile' => $this->getTextMapping(),
            'ranking_quartile_id' => $this->getKeywordMapping(),
        ]);
    }
}