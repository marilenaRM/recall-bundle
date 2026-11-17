<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Card;

use Symfony\AI\Platform\Contract\JsonSchema\Attribute\Schema;

final readonly class Duplicates
{
    /**
     * @param list<string> $ids
     */
    public function __construct(
        #[Schema(description: 'Ids of NEW cards whose knowledge is already tested by another card. Empty when there is none.')]
        public array $ids,
    ) {
    }
}
