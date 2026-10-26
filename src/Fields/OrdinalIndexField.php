<?php

declare(strict_types=1);

namespace Wikibase\Search\Elastic\Fields;

use Wikibase\DataModel\Entity\EntityDocument;

final class OrdinalIndexField extends AbstractEventStatementsIndexField
{
    public const NAME = 'ordinal';

    public function __construct() { parent::__construct(self::NAME); }

    public function getFieldData(EntityDocument $entity)
    {
        $value = $this->getFirstStatementValue($entity, 'ordinal');
        if (!is_numeric($value)) {
            return $value;
        }

        return floor((float)$value) === (float)$value ? (int)$value : (float)$value;
    }

    protected function getFieldMapping(): array
    {
        return ['type' => 'double'];
    }
}