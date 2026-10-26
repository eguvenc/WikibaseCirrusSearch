<?php

declare(strict_types=1);

namespace Wikibase\Search\Elastic\Fields;

use Confident\Helpers\OrganizationPropertiesConfig;
use Wikibase\DataModel\Entity\EntityDocument;

final class OrganizationSuggestionIndexField extends AbstractEventLinkedEntityIndexField
{
    public const NAME = 'organization_suggestion';

    public function __construct()
    {
        parent::__construct(self::NAME);
    }

    public function getFieldData(EntityDocument $entity): array
    {
        $commonProperties = $this->getCommonProperties();
        $organizationProperties = OrganizationPropertiesConfig::get();

        $partOfPropertyId = $commonProperties['part_of'] ?? null;
        $rorPropertyId = $organizationProperties['ror_id'] ?? null;
        $sourceUrlPropertyId = $organizationProperties['source_url'] ?? null;
        
        $rorId = null;
        if ($this->isValidPropertyId($rorPropertyId)) {
            $value = trim(
                $this->getEntityPropertyValue($entity, $rorPropertyId)
            );
            $rorId = $value !== '' ? $value : null;
        }

        $sourceUrl = null;
        if ($this->isValidPropertyId($sourceUrlPropertyId)) {
            $value = trim(
                $this->getEntityPropertyValue($entity, $sourceUrlPropertyId)
            );
            $sourceUrl = $value !== '' ? $value : null;
        }

        $partOfQids = [];
        if ($this->isValidPropertyId($partOfPropertyId)) {
            foreach (
                $this->getLinkedEntitiesByProperty($entity, $partOfPropertyId)
                as $parentEntity
            ) {
                $qid = $this->getEntityId($parentEntity);

                if ($qid !== '') {
                    $partOfQids[] = $qid;
                }
            }
        }

        $partOfQids = array_values(array_unique($partOfQids));

        $searchText = $this->getSearchText($entity);

        // Do not create an empty object for entities that are not useful
        // as organization suggestions.
        if (
            $searchText === []
            && $rorId === null
            && $sourceUrl === null
            && $partOfQids === []
        ) {
            return [];
        }

        return [
            'search_text' => $searchText,
            'ror_id' => $rorId,
            'source_url' => $sourceUrl,
            'part_of_qids' => $partOfQids,
        ];
    }

    /**
     * Get labels and aliases for searching.
     *
     * @return string[]
     */
    private function getSearchText(EntityDocument $entity): array
    {
        $values = [];

        // Labels
        foreach ($entity->getLabels() as $term) {
            $value = trim($term->getText());

            if ($value !== '') {
                $values[] = $value;
            }
        }

        // Aliases (Wikibase 1.44 API)
        foreach ($entity->getFingerprint()->getAliasGroups() as $aliasGroup) {
            foreach ($aliasGroup->getAliases() as $alias) {
                $value = trim($alias);

                if ($value !== '') {
                    $values[] = $value;
                }
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * Retrieve CommonProperties in the extension configuration.
     */
    private function getCommonProperties(): array
    {
        $config = \MediaWiki\MediaWikiServices::getInstance()->getMainConfig();

        $properties = $config->get('CommonProperties');

        if (!is_array($properties)) {
            return [];
        }

        // Support both resolved config and extension.json's wrapped value.
        if (
            isset($properties['value'])
            && is_array($properties['value'])
        ) {
            return $properties['value'];
        }

        return $properties;
    }

    protected function getFieldMapping(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'search_text' => [
                    'type' => 'text',
                ],
                'ror_id' => [
                    'type' => 'keyword',
                ],
                'source_url' => [
                    'type' => 'keyword',
                ],
                'part_of_qids' => [
                    'type' => 'keyword',
                ],
            ],
        ];
    }
}
