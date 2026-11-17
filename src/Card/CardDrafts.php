<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Card;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

final readonly class CardDrafts
{
    /**
     * @param list<CardDraft> $cards
     */
    public function __construct(
        #[Schema(description: 'Empty when the material holds nothing worth remembering.', maxItems: 3)]
        public array $cards,
    ) {
    }
}
