<?php

declare(strict_types=1);

namespace Wikibase\Search\Elastic\Fields;

use Wikibase\DataModel\Entity\EntityDocument;

final class OfficialLinksIndexField extends AbstractEventStatementsIndexField
{
    public const NAME = 'official_links';

    public function __construct() { parent::__construct(self::NAME); }

    public function getFieldData(EntityDocument $entity)
    {
        $rows = [];
        foreach ($this->getStatementsForProperty($entity, 'official_link') as $statement) {
            $url = $this->getMainValue($statement);
            if (!is_string($url) || trim($url) === '') {
                continue;
            }
            $type = $this->getQualifierEntityFields($statement, 'url_type');
            $rows[] = [
                'url_type' => $type['label'],
                'url_type_id' => $type['id'],
                'url' => trim($url),
            ];
        }
        return $rows;
    }

    protected function getFieldMapping(): array
    {
        return $this->getNestedMapping([
            'url_type' => $this->getTextMapping(),
            'url_type_id' => $this->getKeywordMapping(),
            'url' => $this->getKeywordMapping(),
        ]);
    }
}