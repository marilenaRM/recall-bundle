<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Card;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

final readonly class CardDraft
{
    public function __construct(
        #[Schema(description: 'A question that tests understanding of the fact, not rote trivia.')]
        public string $question,
        #[Schema(description: 'The expected answer, three sentences at most, grounded only in the provided material.')]
        public string $answer,
    ) {
    }
}
