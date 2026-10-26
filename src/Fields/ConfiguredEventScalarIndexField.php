<?php

declare(strict_types=1);

namespace Wikibase\Search\Elastic\Fields;

use Wikibase\DataModel\Entity\EntityDocument;

/** A configurable single-property field for direct top-level scalar values. */
final class ConfiguredEventScalarIndexField extends AbstractEventStatementsIndexField
{
    private string $propertyKey;
    private string $kind;

    public function __construct(string $fieldName, string $propertyKey, string $kind = 'keyword')
    {
        parent::__construct($fieldName);
        $this->propertyKey = $propertyKey;
        $this->kind = $kind;
    }

    public function getFieldData(EntityDocument $entity)
    {
        $value = $this->getFirstStatementValue($entity, $this->propertyKey);
        if ($value === null || $value === '') {
            return null;
        }

        if ($this->kind === 'integer' && is_numeric($value)) {
            return (int)$value;
        }
        if ($this->kind === 'number' && is_numeric($value)) {
            return (float)$value;
        }

        return is_scalar($value) ? (string)$value : null;
    }

    protected function getFieldMapping(): array
    {
        if ($this->kind === 'date') {
            return ['type' => 'date'];
        }
        if ($this->kind === 'integer') {
            return ['type' => 'long'];
        }
        if ($this->kind === 'number') {
            return ['type' => 'double'];
        }
        if ($this->kind === 'text') {
            return $this->getTextMapping();
        }

        return $this->getKeywordMapping();
    }
}