<?php

declare(strict_types=1);

namespace Wikibase\Search\Elastic\Fields;

use Confident\Helpers\EventPropertiesConfig;
use DataValues\Geo\Values\GlobeCoordinateValue;
use DataValues\MonolingualTextValue;
use DataValues\TimeValue;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\StatementListProvider;

/**
 * Shared statement/qualifier helpers for custom event index fields.
 *
 * Property IDs are sourced from EventPropertiesConfig. Linked-item labels are
 * resolved from Wikibase entities, replacing hard-coded QID-to-label tables.
 */
abstract class AbstractEventStatementsIndexField extends AbstractEventLinkedEntityIndexField
{
    protected function __construct(string $name)
    {
        parent::__construct($name);
    }

    protected function getConfiguredEventPropertyId(string $key): ?string
    {
        $properties = EventPropertiesConfig::get();
        $propertyId = $properties[$key] ?? null;

        return $this->isValidPropertyId($propertyId) ? $propertyId : null;
    }

    /** @return array */
    protected function getStatementsForProperty(EntityDocument $entity, string $key): array
    {
        $propertyId = $this->getConfiguredEventPropertyId($key);

        if ($propertyId === null || !$entity instanceof StatementListProvider) {
            return [];
        }

        $matches = [];
        foreach ($entity->getStatements() as $statement) {
            $snak = $statement->getMainSnak();
            if (
                $snak instanceof PropertyValueSnak
                && $snak->getPropertyId()->getSerialization() === $propertyId
            ) {
                $matches[] = $statement;
            }
        }

        return $matches;
    }

    protected function getMainValue($statement)
    {
        return $this->getSnakValue($statement->getMainSnak());
    }

    protected function getMainLanguage($statement): ?string
    {
        $snak = $statement->getMainSnak();
        if (!$snak instanceof PropertyValueSnak) {
            return null;
        }

        $value = $snak->getDataValue();
        return $value instanceof MonolingualTextValue
            ? $value->getLanguageCode()
            : null;
    }

    protected function getSnakValue(Snak $snak)
    {
        if (!$snak instanceof PropertyValueSnak) {
            return null;
        }

        $value = $snak->getDataValue();

        if ($value instanceof EntityIdValue) {
            return $value->getEntityId()->getSerialization();
        }
        if ($value instanceof MonolingualTextValue) {
            return $value->getText();
        }
        if ($value instanceof TimeValue) {
            return ltrim($value->getTime(), '+');
        }
        if ($value instanceof GlobeCoordinateValue) {
            return (string)$value->getLatitude() . ',' . (string)$value->getLongitude();
        }
        // QuantityValue implementations expose their amount separately.
        if (is_object($value) && method_exists($value, 'getAmount')) {
            $amount = $value->getAmount();
            if (is_string($amount) || is_numeric($amount)) {
                return $amount;
            }
            if (is_object($amount) && method_exists($amount, 'getValue')) {
                $rawAmount = $amount->getValue();
                if (is_string($rawAmount) || is_numeric($rawAmount)) {
                    return $rawAmount;
                }
            }
        }
        if (is_object($value) && method_exists($value, 'getValue')) {
            $raw = $value->getValue();
            if (is_string($raw) || is_numeric($raw)) {
                return $raw;
            }
        }
        if (is_object($value) && method_exists($value, 'getText')) {
            return $value->getText();
        }

        return null;
    }

    /** Return the first qualifier value for a configured property key. */
    protected function getQualifierValue($statement, string $key): ?string
    {
        $propertyId = $this->getConfiguredEventPropertyId($key);
        if ($propertyId === null) {
            return null;
        }

        foreach ($statement->getQualifiers() as $qualifier) {
            if (
                $qualifier->getPropertyId()->getSerialization() !== $propertyId
                || !$qualifier instanceof PropertyValueSnak
            ) {
                continue;
            }

            $value = $this->getSnakValue($qualifier);
            if (is_string($value) || is_numeric($value)) {
                return (string)$value;
            }
        }

        return null;
    }

    /**
     * Return the first qualifier as both QID/value and display label.
     * The ID is preserved even if the linked entity or its label is missing.
     */
    protected function getQualifierEntityFields($statement, string $key): array
    {
        $id = $this->getQualifierValue($statement, $key);
        return [
            'label' => $id !== null ? $this->resolveItemLabel($id) : null,
            'id' => $id,
        ];
    }

    protected function resolveItemLabel(string $qid): ?string
    {
        if (preg_match('/^Q[1-9][0-9]*$/', $qid) !== 1) {
            return null;
        }

        try {
            $item = $this->loadItemEntity(new ItemId($qid));
            if ($item === null) {
                return null;
            }

            $label = trim($this->getEntityLabel($item));
            return $label !== '' ? $label : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function resolveItemUuid(string $qid): ?string
    {
        if (preg_match('/^Q[1-9][0-9]*$/', $qid) !== 1) {
            return null;
        }

        try {
            $item = $this->loadItemEntity(new ItemId($qid));
            if ($item === null) {
                return null;
            }

            $uuid = trim($this->getConfiguredEntityPropertyValue($item, 'uuid'));
            return $uuid !== '' ? $uuid : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function getFirstStatementValue(EntityDocument $entity, string $key)
    {
        $statements = $this->getStatementsForProperty($entity, $key);
        if ($statements === []) {
            return null;
        }

        return $this->getMainValue($statements[0]);
    }

    protected function getNestedMapping(array $properties): array
    {
        return [
            'type' => 'nested',
            'properties' => $properties,
        ];
    }

    protected function getKeywordMapping(): array
    {
        return ['type' => 'keyword'];
    }
}