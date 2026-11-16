<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Review;

use MarilenaRM\RecallBundle\Card\Card;

final class Progress
{
    /**
     * @param array<string, MemoryState> $states card id => memory state
     */
    public function __construct(
        private array $states = [],
    ) {
    }

    public function stateOf(Card $card): ?MemoryState
    {
        return $this->states[$card->id] ?? null;
    }

    public function update(Card $card, MemoryState $state): void
    {
        $this->states[$card->id] = $state;
    }

    /**
     * @return array<string, MemoryState>
     */
    public function states(): array
    {
        return $this->states;
    }

    /**
     * Cards due by the end of the day first, most overdue first, then cards never reviewed, capped at $newLimit.
     *
     * @param list<Card> $cards
     *
     * @return list<Card>
     */
    public function dueCards(array $cards, \DateTimeImmutable $now, int $newLimit): array
    {
        $due = $new = [];
        $endOfDay = $now->setTime(23, 59, 59);

        foreach ($cards as $card) {
            $state = $this->stateOf($card);

            if (null === $state) {
                $new[] = $card;
            } elseif ($state->isDue($endOfDay)) {
                $due[] = $card;
            }
        }

        usort($due, fn (Card $a, Card $b): int => $this->stateOf($a)?->due <=> $this->stateOf($b)?->due);

        return [...$due, ...\array_slice($new, 0, $newLimit)];
    }
}
