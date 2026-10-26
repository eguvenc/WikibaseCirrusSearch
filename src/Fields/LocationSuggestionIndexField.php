<?php

declare(strict_types=1);

namespace Wikibase\Search\Elastic\Fields;

use Confident\Helpers\LocationPropertiesConfig;
use Wikibase\DataModel\Entity\EntityDocument;

/**
 * Indexes country/state metadata on location entities themselves.
 *
 * This is intentionally separate from LocationIndexField: that field stores
 * locations nested under an event, while SuggestLocation returns location
 * entities as individual suggestions.
 */
final class LocationSuggestionIndexField extends AbstractEventLinkedEntityIndexField
{
    public const NAME = 'location_suggestion';

    public function __construct()
    {
        parent::__construct(self::NAME);
    }

    public function getFieldData(EntityDocument $entity): array
    {
        $properties = LocationPropertiesConfig::get();

        $countryPropertyId = $properties['country'] ?? null;
        $countryIsoPropertyId = $properties['country_iso'] ?? null;
        $statePropertyId = $properties['state'] ?? null;
        $stateDbIdPropertyId = $properties['state_db_id'] ?? null;

        // Some location entities store identifiers directly; others refer to
        // country/state items. Support both layouts so this works for both
        // individual country/state suggestions and city/venue suggestions.
        $countryQid = null;
        $countryIso = null;
        if ($this->isValidPropertyId($countryIsoPropertyId)) {
            $ownCountryIso = trim($this->getEntityPropertyValue($entity, $countryIsoPropertyId));
            $countryIso = $ownCountryIso !== '' ? strtolower($ownCountryIso) : null;
        }

        if ($this->isValidPropertyId($countryPropertyId)) {
            $countries = $this->getLinkedEntitiesByProperty($entity, $countryPropertyId);
            if ($countries !== []) {
                $country = $countries[0];
                $countryQid = $this->getEntityId($country);

                // Prefer a direct ISO value on the current entity; otherwise
                // resolve the ISO code from the linked country item.
                if ($countryIso === null && $this->isValidPropertyId($countryIsoPropertyId)) {
                    $iso = trim($this->getEntityPropertyValue($country, $countryIsoPropertyId));
                    $countryIso = $iso !== '' ? strtolower($iso) : null;
                }
            }
        }

        $stateQid = null;
        $stateId = null;
        if ($this->isValidPropertyId($stateDbIdPropertyId)) {
            $ownStateId = trim($this->getEntityPropertyValue($entity, $stateDbIdPropertyId));
            $stateId = $ownStateId !== '' ? $ownStateId : null;
        }

        if ($this->isValidPropertyId($statePropertyId)) {
            $states = $this->getLinkedEntitiesByProperty($entity, $statePropertyId);
            if ($states !== []) {
                $state = $states[0];
                $stateQid = $this->getEntityId($state);

                // Prefer a direct state DB ID; fall back to the linked state item.
                if ($stateId === null && $this->isValidPropertyId($stateDbIdPropertyId)) {
                    $externalId = trim($this->getEntityPropertyValue($state, $stateDbIdPropertyId));
                    $stateId = $externalId !== '' ? $externalId : null;
                }
            }
        }

        if ($countryQid === null && $countryIso === null && $stateQid === null && $stateId === null) {
            return [];
        }

        return [
            'country_qid' => $countryQid !== '' ? $countryQid : null,
            'country_iso' => $countryIso,
            'state_qid' => $stateQid !== '' ? $stateQid : null,
            'state_id' => $stateId,
        ];
    }

    protected function getFieldMapping(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'country_qid' => ['type' => 'keyword'],
                'country_iso' => ['type' => 'keyword'],
                'state_qid' => ['type' => 'keyword'],
                'state_id' => ['type' => 'keyword'],
            ],
        ];
    }
}