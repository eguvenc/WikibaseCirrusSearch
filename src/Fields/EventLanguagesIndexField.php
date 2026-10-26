<?php

declare(strict_types=1);

namespace Wikibase\Search\Elastic\Fields;

use Confident\Helpers\CommonPropertiesConfig;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\ItemId;

final class EventLanguagesIndexField extends AbstractEventStatementsIndexField
{
    public const NAME = 'event_languages';

    public function __construct() { parent::__construct(self::NAME); }

    public function getFieldData(EntityDocument $entity)
    {
        $rows = [];
        foreach ($this->getStatementsForProperty($entity, 'event_language') as $statement) {
            $id = $this->getMainValue($statement);
            if (!is_string($id) || preg_match('/^Q[1-9][0-9]*$/', $id) !== 1) {
                continue;
            }

            try {
                $languageEntity = $this->loadItemEntity(new ItemId($id));
            } catch (\Throwable $e) {
                $languageEntity = null;
            }
            if ($languageEntity === null) {
                continue;
            }
            $commonProperties = CommonPropertiesConfig::get();
            $isoPropertyId = $commonProperties['language_iso'] ?? null;
            $iso = $isoPropertyId !== null ? $this->getEntityPropertyValue($languageEntity, $isoPropertyId) : '';

            $rows[$id] = [
                'id' => $id,
                'iso' => is_string($iso) && trim($iso) !== '' ? strtolower(trim($iso)) : null,
                'name' => $this->getEntityLabel($languageEntity),
            ];
        }

        return array_values($rows);
    }

    protected function getFieldMapping(): array
    {
        return $this->getNestedMapping([
            'id' => $this->getKeywordMapping(),
            'iso' => $this->getKeywordMapping(),
            'name' => $this->getTextMapping(),
        ]);
    }
}