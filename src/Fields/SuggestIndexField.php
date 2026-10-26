<?php

declare(strict_types=1);

namespace Wikibase\Search\Elastic\Fields;

use Wikibase\DataModel\Entity\EntityDocument;

final class SuggestIndexField extends AbstractEventLinkedEntityIndexField
{
    public const NAME = 'suggest';

    public function __construct()
    {
        parent::__construct(self::NAME);
    }

    public function getFieldData(EntityDocument $entity): array
    {
        $searchTexts = [];

        foreach ($entity->getLabels() as $label) {
            $text = trim($label->getText());

            if ($text !== '') {
                $searchTexts[] = $text;
            }
        }

        foreach ($entity->getFingerprint()->getAliasGroups() as $aliasGroup) {
            foreach ($aliasGroup->getAliases() as $alias) {
                $text = trim($alias);

                if ($text !== '') {
                    $searchTexts[] = $text;
                }
            }
        }

        return [
            'search_text' => implode(
                ' ',
                array_values(array_unique($searchTexts))
            ),
        ];
    }
    
    protected function getFieldMapping(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'search_text' => [
                    'type' => 'text',
                ],
            ],
        ];
    }
}
