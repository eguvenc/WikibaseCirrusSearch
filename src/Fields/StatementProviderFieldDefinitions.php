<?php

declare(strict_types=1);

namespace Wikibase\Search\Elastic\Fields;

use Psr\Log\LoggerInterface;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\Lib\DataTypeFactory;
use Wikibase\Lib\SettingsArray;
use Wikibase\Repo\Search\Fields\FieldDefinitions;
use Wikibase\Repo\Search\Fields\WikibaseIndexField;

/** Fields for an object that has statements. */
class StatementProviderFieldDefinitions implements FieldDefinitions
{
    private $propertyIds;
    private $searchIndexDataFormatters;
    private $dataTypeFactory;
    private $propertyDataTypeLookup;
    private $indexedTypes;
    private $excludedIds;
    private $allowedQualifierPropertyIdsForQuantityStatements;
    private ?LoggerInterface $logger;
    private $statementProvider;

    public function __construct(
        DataTypeFactory $dataTypeFactory,
        PropertyDataTypeLookup $propertyDataTypeLookup,
        array $searchIndexDataFormatters,
        array $propertyIds,
        array $indexedTypes,
        array $excludedIds,
        array $allowedQualifierPropertyIdsForQuantityStatements,
        ?LoggerInterface $logger = null,
        ?callable $statementProvider = null
    ) {
        $this->propertyIds = $propertyIds;
        $this->searchIndexDataFormatters = $searchIndexDataFormatters;
        $this->dataTypeFactory = $dataTypeFactory;
        $this->propertyDataTypeLookup = $propertyDataTypeLookup;
        $this->indexedTypes = $indexedTypes;
        $this->excludedIds = $excludedIds;
        $this->allowedQualifierPropertyIdsForQuantityStatements =
            $allowedQualifierPropertyIdsForQuantityStatements;
        $this->logger = $logger;
        $this->statementProvider = $statementProvider;
    }

    /**
     * @return WikibaseIndexField[] Key is field name, value is WikibaseIndexField.
     */
    public function getFields()
    {
        $fields = [
            StatementsField::NAME => new StatementsField(
                $this->dataTypeFactory,
                $this->propertyDataTypeLookup,
                $this->propertyIds,
                $this->indexedTypes,
                $this->excludedIds,
                $this->searchIndexDataFormatters,
                $this->logger,
                $this->statementProvider
            ),
            // Existing linked-entity fields.
            InstanceOfIndexField::NAME => new InstanceOfIndexField(),
            AcademicFieldIndexField::NAME => new AcademicFieldIndexField(),
            LocationIndexField::NAME => new LocationIndexField(),
            LocationSuggestionIndexField::NAME => new LocationSuggestionIndexField(),
            OrganizationSuggestionIndexField::NAME => new OrganizationSuggestionIndexField(),
            SuggestIndexField::NAME => new SuggestIndexField(),
            MaintainerIndexField::NAME => new MaintainerIndexField(),
            PartOfSeriesIndexField::NAME => new PartOfSeriesIndexField(),
            
            // Direct event fields.
            TitlesIndexField::NAME => new TitlesIndexField(),
            AcronymsIndexField::NAME => new AcronymsIndexField(),
            OrdinalIndexField::NAME => new OrdinalIndexField(),
            EventModeIndexField::NAME => new EventModeIndexField(),
            EventTypeIndexField::NAME => new EventTypeIndexField(),
            RecordStatusIndexField::NAME => new RecordStatusIndexField(),
            EventLanguagesIndexField::NAME => new EventLanguagesIndexField(),
            OfficialLinksIndexField::NAME => new OfficialLinksIndexField(),
            DeadlinesIndexField::NAME => new DeadlinesIndexField(),
            AlternativeIdsIndexField::NAME => new AlternativeIdsIndexField(),
            RelatedIdsIndexField::NAME => new RelatedIdsIndexField(),
            RelatedEventsIndexField::NAME => new RelatedEventsIndexField(),
            RelatedExternalEventsIndexField::NAME => new RelatedExternalEventsIndexField(),
            MetricsIndexField::NAME => new MetricsIndexField(),
            RankingsIndexField::NAME => new RankingsIndexField(),

            // Direct scalar fields.
            'uuid' => new ConfiguredEventScalarIndexField('uuid', 'uuid', 'keyword'),
            'doi' => new ConfiguredEventScalarIndexField('doi', 'doi', 'keyword'),
            'start_date' => new ConfiguredEventScalarIndexField('start_date', 'start_date', 'date'),
            'end_date' => new ConfiguredEventScalarIndexField('end_date', 'end_date', 'date'),
            'number_of_tracks' => new ConfiguredEventScalarIndexField('number_of_tracks', 'number_of_tracks', 'integer'),
            'number_of_attendees' => new ConfiguredEventScalarIndexField('number_of_attendees', 'number_of_attendees', 'integer'),
            'number_of_countries_represented' => new ConfiguredEventScalarIndexField('number_of_countries_represented', 'number_of_countries_represented', 'integer'),
            'created_at' => new ConfiguredEventScalarIndexField('created_at', 'created_at', 'date'),
            'updated_at' => new ConfiguredEventScalarIndexField('updated_at', 'updated_at', 'date'),

            StatementCountField::NAME => new StatementCountField(),
        ];

        if ($this->allowedQualifierPropertyIdsForQuantityStatements) {
            $fields[StatementQuantityField::NAME] = new StatementQuantityField(
                $this->dataTypeFactory,
                $this->propertyDataTypeLookup,
                $this->propertyIds,
                $this->indexedTypes,
                $this->excludedIds,
                $this->searchIndexDataFormatters,
                $this->allowedQualifierPropertyIdsForQuantityStatements,
                $this->logger
            );
        }

        return $fields;
    }

    public static function newFromSettings(
        DataTypeFactory $dataTypeFactory,
        PropertyDataTypeLookup $propertyDataTypeLookup,
        array $searchIndexDataFormatters,
        SettingsArray $settings,
        ?LoggerInterface $logger = null,
        ?callable $statementProvider = null
    ): self {
        return new static(
            $dataTypeFactory,
            $propertyDataTypeLookup,
            $searchIndexDataFormatters,
            $settings->getSetting('searchIndexProperties'),
            $settings->getSetting('searchIndexTypes'),
            $settings->getSetting('searchIndexPropertiesExclude'),
            $settings->getSetting('searchIndexQualifierPropertiesForQuantity'),
            $logger,
            $statementProvider
        );
    }
}