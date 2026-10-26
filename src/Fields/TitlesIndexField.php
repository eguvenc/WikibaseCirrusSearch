<?php

declare(strict_types=1);

namespace Wikibase\Search\Elastic\Fields;

use DataValues\MonolingualTextValue;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Snak\PropertyValueSnak;

final class TitlesIndexField extends AbstractEventStatementsIndexField
{
    public const NAME = 'titles';

    public function __construct() { parent::__construct(self::NAME); }

    public function getFieldData(EntityDocument $entity)
    {
        $rows = [];
        $seen = [];

        foreach ($this->getStatementsForProperty($entity, 'title') as $statement) {
            $snak = $statement->getMainSnak();
            if (!$snak instanceof PropertyValueSnak) {
                continue;
            }
            $value = $snak->getDataValue();
            if (!$value instanceof MonolingualTextValue) {
                continue;
            }

            $title = trim($value->getText());
            $lang = trim($value->getLanguageCode());
            if ($title === '' || $lang === '') {
                continue;
            }

            $key = $lang . '|' . $title;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $titleTypeId = $this->getQualifierValue($statement, 'title_type');
            $titleTypeName = $titleTypeId !== null ? $this->resolveItemLabel($titleTypeId) : null;
            
            $rows[] = [
                'title' => $title,
                'title_type' => $titleTypeId !== null ? $this->resolveItemLabel($titleTypeId) : null,
                'title_type_id' => $titleTypeId,
                'lang' => $lang,
            ];
        }

        return $rows;
    }

    protected function getFieldMapping(): array
    {
        return $this->getNestedMapping([
            'title' => $this->getTextMapping(),
            'title_type' => $this->getTextMapping(),
            'title_type_id' => $this->getKeywordMapping(),
            'lang' => $this->getKeywordMapping(),
        ]);
    }
}