<?php

declare(strict_types=1);

namespace Wikibase\Search\Elastic\Fields;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementListProvider;

final class InstanceOfIndexField extends AbstractEventLinkedEntityIndexField
{
    public const NAME = 'instance_of';

    public function __construct()
    {
        parent::__construct(self::NAME);
    }

    public function getFieldData(EntityDocument $entity): array
    {
        $propertyId = $this->getCommonPropertyId('instance_of');

        if (
            $propertyId === null
            || !$entity instanceof StatementListProvider
        ) {
            return [];
        }

        $values = [];

        foreach ($entity->getStatements() as $statement) {
            $snak = $statement->getMainSnak();

            if (
                !$snak instanceof PropertyValueSnak
                || $snak->getPropertyId()->getSerialization() !== $propertyId
            ) {
                continue;
            }

            $dataValue = $snak->getDataValue();

            if (!$dataValue instanceof EntityIdValue) {
                continue;
            }

            $itemId = $dataValue->getEntityId();

            if ($itemId instanceof ItemId) {
                $values[] = $itemId->getSerialization();
            }
        }

        return array_values(array_unique($values));
    }

    protected function getFieldMapping(): array
    {
        return [
            'type' => 'keyword',
        ];
    }
}