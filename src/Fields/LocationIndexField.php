<?php

declare(strict_types=1);

namespace Wikibase\Search\Elastic\Fields;

use Confident\Helpers\LocationPropertiesConfig;
use DataValues\Geo\Values\GlobeCoordinateValue;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Statement\StatementListProvider;

/**
 * Structured location field.
 *
 * Each event location contains its location-specific details and its linked
 * country. Country `id`/`iso` use the ISO code; `qid` preserves the Wikibase
 * item ID for filters that need to use the QID.
 */
final class LocationIndexField extends AbstractEventLinkedEntityIndexField
{
    public const NAME = 'location';

    public function __construct()
    {
        parent::__construct(self::NAME);
    }

    public function getFieldData(EntityDocument $entity)
    {
        $eventLocationPropertyId = LocationPropertiesConfig::getEventLocationPropertyId();
        if (!$this->isValidPropertyId($eventLocationPropertyId)) {
            return [];
        }

        $locationEntities = $this->getLinkedEntitiesByProperty(
            $entity,
            $eventLocationPropertyId
        );

        $config = LocationPropertiesConfig::get();
        $countryPropertyId = $config['country'] ?? null;
        $continentPropertyId = $config['continent'] ?? null;
        $locatedInContinentPropertyId = $config['located_in_continent'] ?? null;
        $statePropertyId = $config['state'] ?? null;
        $stateDbIdPropertyId = $config['state_db_id'] ?? null;
        $cityPropertyId = $config['city'] ?? null;
        $cityDbIdPropertyId = $config['city_db_id'] ?? null;
        $regionPropertyId = $config['region'] ?? null;
        $venuePropertyId = $config['venue'] ?? null;
        $continentCodePropertyId = $config['continent_code'] ?? null;
        $coordinatesPropertyId = $config['coordinates'] ?? null;
        $countryIsoPropertyId = $config['country_iso'] ?? null;

        $rows = [];

        foreach ($locationEntities as $locationEntity) {
            $coordinateValue = $this->getCoordinateValue(
                $locationEntity,
                $coordinatesPropertyId
            );
            $coordinates = $coordinateValue !== null
                ? (string) $coordinateValue->getLatitude()
                    . ','
                    . (string) $coordinateValue->getLongitude()
                : null;
            // geo_point for distance filters and sorting (geo_distance).
            $geo = $this->toGeoPoint($coordinateValue);

            $continentCode = $this->getEntityPropertyValue(
                $locationEntity,
                $continentCodePropertyId
            );

            $continent = $this->firstLinkedEntityData(
                $locationEntity,
                $continentPropertyId
            );

            // Read each external identifier from its own linked entity and
            // configured property: state_db_id for state, city_db_id for city.
            $state = $this->firstLinkedEntityData(
                $locationEntity,
                $statePropertyId,
                $stateDbIdPropertyId
            );

            $city = $this->firstLinkedEntityData(
                $locationEntity,
                $cityPropertyId,
                $cityDbIdPropertyId
            );

            $region = $this->firstLinkedEntityData(
                $locationEntity,
                $regionPropertyId
            );

            // Venue is a string-valued property on the location entity, not a
            // linked Wikibase item, so do not resolve it as an entity.
            $venueValue = trim($this->getEntityPropertyValue(
                $locationEntity,
                $venuePropertyId
            ));
            $venue = $venueValue !== '' ? $venueValue : null;

            // If the location has no explicit continent link, derive it from
            // its country using the configured located_in_continent property.
            $countryEntities = $this->getLinkedEntitiesByProperty(
                $locationEntity,
                $countryPropertyId
            );

            if ($continent === null && $this->isValidPropertyId($locatedInContinentPropertyId)) {
                foreach ($countryEntities as $countryEntity) {
                    $continent = $this->firstLinkedEntityData(
                        $countryEntity,
                        $locatedInContinentPropertyId
                    );

                    if ($continent !== null) {
                        break;
                    }
                }
            }

            if ($continent !== null && empty($continent['code']) && $continentCode !== '') {
                $continent['code'] = strtolower($continentCode);
            }

            $baseRow = [
                'continent' => $continent,
                'continent_code' => $continentCode !== ''
                    ? strtolower($continentCode)
                    : ($continent['code'] ?? null),
                'state' => $state,
                'city' => $city,
                'region' => $region,
                'venue' => $venue,
                'coordinates' => $coordinates,
                'geo' => $geo,
            ];

            if ($countryEntities === []) {
                $rows[] = ['country' => null] + $baseRow;
                continue;
            }

            foreach ($countryEntities as $countryEntity) {
                $countryIso = strtolower(trim(
                    $this->getEntityPropertyValue(
                        $countryEntity,
                        $countryIsoPropertyId
                    )
                ));

                $country = [
                    // The API uses ISO as the country ID. Keep the Wikibase
                    // QID separately for filtering and entity references.
                    'id' => $countryIso !== '' ? $countryIso : null,
                    'iso' => $countryIso !== '' ? $countryIso : null,
                    'qid' => $this->getEntityId($countryEntity),
                    'name' => $this->getEntityLabel($countryEntity),
                ];

                $rows[] = ['country' => $country] + $baseRow;
            }
        }

        return $rows;
    }

    /**
     * Resolve a linked entity into its ID, label, optional UUID/code and an
     * optional external identifier (for example the configured state_db_id).
     */
    private function firstLinkedEntityData(
        EntityDocument $entity,
        $propertyId,
        ?string $externalIdPropertyId = null
    ): ?array {
        if (!$this->isValidPropertyId($propertyId)) {
            return null;
        }

        $linked = $this->getLinkedEntitiesByProperty($entity, $propertyId);
        if ($linked === []) {
            return null;
        }

        $item = $linked[0];
        $id = $this->getEntityId($item);
        if ($id === '') {
            return null;
        }

        $data = [
            'id' => $id,
            'name' => $this->getEntityLabel($item),
        ];

        $uuid = $this->getConfiguredEntityPropertyValue($item, 'uuid');
        if ($uuid !== '') {
            $data['uuid'] = $uuid;
        }

        $config = LocationPropertiesConfig::get();
        $continentCodePropertyId = $config['continent_code'] ?? null;
        if ($this->isValidPropertyId($continentCodePropertyId)) {
            $code = $this->getEntityPropertyValue($item, $continentCodePropertyId);
            if ($code !== '') {
                $data['code'] = strtolower($code);
            }
        }

        if ($this->isValidPropertyId($externalIdPropertyId)) {
            // External identifier values are scalar strings. Resolve this
            // property on the linked state/city item itself, not on the venue.
            $externalId = trim($this->getEntityPropertyValue(
                $item,
                $externalIdPropertyId
            ));
            $data['db_id'] = $externalId !== '' ? $externalId : null;
        }

        return $data;
    }

    private function getCoordinateValue(
        EntityDocument $entity,
        $propertyId
    ): ?GlobeCoordinateValue {
        if (
            !$this->isValidPropertyId($propertyId)
            || !$entity instanceof StatementListProvider
        ) {
            return null;
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
            if ($value instanceof GlobeCoordinateValue) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Elasticsearch geo_point, or null when the value is missing or out of
     * range (an invalid point would make the whole document fail to index).
     *
     * @return array{lat: float, lon: float}|null
     */
    private function toGeoPoint(?GlobeCoordinateValue $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $lat = (float) $value->getLatitude();
        $lon = (float) $value->getLongitude();

        if ($lat < -90.0 || $lat > 90.0 || $lon < -180.0 || $lon > 180.0) {
            return null;
        }

        return ['lat' => $lat, 'lon' => $lon];
    }

    protected function getFieldMapping(): array
    {
        $linkedLocationProperties = [
            'id' => ['type' => 'keyword'],
            'uuid' => ['type' => 'keyword'],
            'name' => $this->getTextMapping(),
        ];

        return [
            'type' => 'nested',
            'properties' => [
                'country' => [
                    'type' => 'object',
                    'properties' => [
                        // ISO is the public country ID; QID remains available
                        // separately for queries that filter by Wikibase item.
                        'id' => ['type' => 'keyword'],
                        'iso' => ['type' => 'keyword'],
                        'qid' => ['type' => 'keyword'],
                        'name' => $this->getTextMapping(),
                    ],
                ],
                'continent' => [
                    'type' => 'object',
                    'properties' => $linkedLocationProperties + [
                        'code' => ['type' => 'keyword'],
                    ],
                ],
                'continent_code' => ['type' => 'keyword'],
                'state' => [
                    'type' => 'object',
                    'properties' => $linkedLocationProperties + [
                        'db_id' => ['type' => 'keyword'],
                    ],
                ],
                'city' => [
                    'type' => 'object',
                    'properties' => $linkedLocationProperties + [
                        'db_id' => ['type' => 'keyword'],
                    ],
                ],
                'region' => [
                    'type' => 'object',
                    'properties' => $linkedLocationProperties,
                ],
                // Venue is a string property rather than a linked entity.
                'venue' => $this->getTextMapping(),
                // "lat,lon" string, kept for API responses.
                'coordinates' => ['type' => 'keyword'],
                // Same point for geo_distance filters and distance sorting.
                'geo' => ['type' => 'geo_point'],
            ],
        ];
    }
}