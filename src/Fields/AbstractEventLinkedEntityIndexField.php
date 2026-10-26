<?php

declare(strict_types=1);

namespace Wikibase\Search\Elastic\Fields;

use CirrusSearch\CirrusSearch;
use Confident\Helpers\CommonPropertiesConfig;
use Confident\Helpers\EventPropertiesConfig;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SearchEngine;
use SearchIndexField;
use SearchIndexFieldDefinition;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementListProvider;
use Wikibase\Lib\Store\LookupConstants;
use Wikibase\Repo\Search\Fields\WikibaseIndexField;
use Wikibase\Repo\WikibaseRepo;

abstract class AbstractEventLinkedEntityIndexField
    extends SearchIndexFieldDefinition
    implements WikibaseIndexField
{
    protected LoggerInterface $logger;

    /** @var array<string, EntityDocument|null> */
    private array $entityCache = [];

    protected function __construct(string $name)
    {
        parent::__construct(
            $name,
            SearchIndexField::INDEX_TYPE_KEYWORD
        );

        $this->logger = new NullLogger();
    }

    public function getMappingField(SearchEngine $engine, $name)
    {
        return $engine instanceof CirrusSearch ? $this : null;
    }

    public function getMapping(SearchEngine $engine)
    {
        if (!$engine instanceof CirrusSearch) {
            return [];
        }

        return $this->getFieldMapping();
    }

    abstract protected function getFieldMapping(): array;

    /**
     * Retrieve an event property ID from EventPropertiesConfig.
     */
    protected function getEventPropertyId(string $key): ?string
    {
        $properties = EventPropertiesConfig::get();
        $propertyId = $properties[$key] ?? null;

        return $this->isValidPropertyId($propertyId)
            ? $propertyId
            : null;
    }

    /**
     * Retrieve an event property ID from CommonPropertiesConfig.
     */
    protected function getCommonPropertyId(string $key): ?string
    {
        $properties = CommonPropertiesConfig::get();
        $propertyId = $properties[$key] ?? null;

        return $this->isValidPropertyId($propertyId)
            ? $propertyId
            : null;
    }

    protected function isValidPropertyId($propertyId): bool
    {
        return is_string($propertyId)
            && preg_match('/^P[1-9][0-9]*$/', $propertyId) === 1;
    }

    /**
     * Resolve linked item entities from statements using a property ID.
     *
     * The property must contain an ItemId value. Other entity types
     * and non-entity values are ignored.
     *
     * @return EntityDocument[]
     */
    protected function getLinkedEntitiesByProperty(
        EntityDocument $sourceEntity,
        ?string $propertyId
    ): array {
        if (
            !$this->isValidPropertyId($propertyId)
            || !$sourceEntity instanceof StatementListProvider
        ) {
            return [];
        }

        $entities = [];

        foreach ($sourceEntity->getStatements() as $statement) {
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

            $entityId = $dataValue->getEntityId();

            if (!$entityId instanceof ItemId) {
                continue;
            }

            $linkedEntity = $this->loadItemEntity($entityId);

            if ($linkedEntity === null) {
                continue;
            }

            $serialization = $entityId->getSerialization();
            $entities[$serialization] = $linkedEntity;
        }

        return array_values($entities);
    }

    protected function loadItemEntity(ItemId $itemId): ?EntityDocument
    {
        $serialization = $itemId->getSerialization();

        if (array_key_exists($serialization, $this->entityCache)) {
            return $this->entityCache[$serialization];
        }

        try {
            $revision = WikibaseRepo::getEntityRevisionLookup()
                ->getEntityRevision(
                    $itemId,
                    0,
                    LookupConstants::LATEST_FROM_MASTER
                );

            if ($revision === null) {
                return $this->entityCache[$serialization] = null;
            }

            $entity = $revision->getEntity();

            if (!$entity instanceof EntityDocument) {
                return $this->entityCache[$serialization] = null;
            }

            return $this->entityCache[$serialization] = $entity;
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Linked entity lookup failed.',
                [
                    'entity' => $serialization,
                    'error' => $e->getMessage(),
                ]
            );

            return $this->entityCache[$serialization] = null;
        }
    }

    protected function getEntityId(EntityDocument $entity): string
    {
        if (!method_exists($entity, 'getId')) {
            return '';
        }

        $id = $entity->getId();

        return is_object($id) && method_exists($id, 'getSerialization')
            ? $id->getSerialization()
            : '';
    }

    /**
     * Return an entity label.
     *
     * Language preference: English, German, then the first available label.
     */
    protected function getEntityLabel(EntityDocument $entity): string
    {
        $labels = null;

        if (method_exists($entity, 'getLabels')) {
            $labels = $entity->getLabels();
        } elseif (method_exists($entity, 'getFingerprint')) {
            $fingerprint = $entity->getFingerprint();

            if (
                is_object($fingerprint)
                && method_exists($fingerprint, 'getLabels')
            ) {
                $labels = $fingerprint->getLabels();
            }
        }

        if ($labels === null) {
            return '';
        }

        if (method_exists($labels, 'getByLanguage')) {
            foreach (['en', 'de'] as $languageCode) {
                try {
                    $term = $labels->getByLanguage($languageCode);

                    if (
                        is_object($term)
                        && method_exists($term, 'getText')
                    ) {
                        $text = trim($term->getText());

                        if ($text !== '') {
                            return $text;
                        }
                    }
                } catch (\Throwable $e) {
                    // Try the next preferred language.
                }
            }
        }

        if (is_iterable($labels)) {
            foreach ($labels as $term) {
                if (
                    is_object($term)
                    && method_exists($term, 'getText')
                ) {
                    $text = trim($term->getText());

                    if ($text !== '') {
                        return $text;
                    }
                }
            }
        }

        return '';
    }

    /**
     * Read the first usable value for a property from an entity.
     *
     * This method reads direct main-snak values, not qualifiers.
     */
    protected function getEntityPropertyValue(
        EntityDocument $entity,
        ?string $propertyId
    ): string {
        if (
            !$this->isValidPropertyId($propertyId)
            || !$entity instanceof StatementListProvider
        ) {
            return '';
        }

        foreach ($entity->getStatements() as $statement) {
            $snak = $statement->getMainSnak();

            if (
                !$snak instanceof PropertyValueSnak
                || $snak->getPropertyId()->getSerialization() !== $propertyId
            ) {
                continue;
            }

            $value = $snak->getDataValue();

            if ($value instanceof EntityIdValue) {
                return $value->getEntityId()->getSerialization();
            }

            if (is_object($value) && method_exists($value, 'getValue')) {
                $scalar = $value->getValue();

                if (is_string($scalar) || is_numeric($scalar)) {
                    $scalar = trim((string)$scalar);

                    if ($scalar !== '') {
                        return $scalar;
                    }
                }
            }

            if (is_object($value) && method_exists($value, 'getText')) {
                $text = trim($value->getText());

                if ($text !== '') {
                    return $text;
                }
            }
        }

        return '';
    }

    protected function getConfiguredEntityPropertyValue(
        EntityDocument $entity,
        string $key
    ): string {
        return $this->getEntityPropertyValue(
            $entity,
            $this->getEventPropertyId($key)
        );
    }

    protected function getTextMapping(): array
    {
        return [
            'type' => 'text',
            'analyzer' => 'lowercase_keyword',
            'fields' => [
                'keyword' => ['type' => 'keyword'],
            ],
        ];
    }
}