<?php

declare(strict_types=1);

namespace Wikibase\Search\Elastic\Fields;

use Wikibase\DataModel\Entity\EntityDocument;

final class AcronymsIndexField extends AbstractEventStatementsIndexField
{
    public const NAME = 'acronyms';

    public function __construct() { parent::__construct(self::NAME); }

    public function getFieldData(EntityDocument $entity)
    {
        $values = [];
        foreach ($this->getStatementsForProperty($entity, 'acronym') as $statement) {
            $value = $this->getMainValue($statement);
            if (!is_string($value) && !is_numeric($value)) {
                continue;
            }
            $value = trim((string)$value);
            if ($value !== '') {
                $values[$value] = $value;
            }
        }

        return array_values($values);
    }

    protected function getFieldMapping(): array
    {
        return [
            'type' => 'text',
            'analyzer' => 'lowercase_keyword',
            'fields' => ['keyword' => ['type' => 'keyword']],
        ];
    }
}