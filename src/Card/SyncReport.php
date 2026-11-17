<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Card;

use MarilenaRM\RecallBundle\Fact\Fact;

final readonly class SyncReport
{
    /**
     * @param list<Fact>   $unchanged  facts whose cards are still accurate
     * @param list<Fact>   $toGenerate new facts, and facts whose source changed since their cards were written
     * @param list<string> $vanished   ids of facts no longer found in the project
     * @param list<Card>   $obsolete   cards of changed or vanished facts
     */
    public function __construct(
        public array $unchanged,
        public array $toGenerate,
        public array $vanished,
        public array $obsolete,
    ) {
    }
}
