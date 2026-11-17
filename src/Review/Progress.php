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
     * New cards are interleaved: one card per fact before any second card, alternating the kinds of
     * fact, so that a session does not walk through the deck one record at a time.
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

        return [...$due, ...\array_slice($this->interleave($new), 0, $newLimit)];
    }

    /**
     * @param list<Card> $cards
     *
     * @return list<Card>
     */
    private function interleave(array $cards): array
    {
        $kindOrder = $factOrder = $factsPerKind = $cardsPerFact = $keys = [];

        foreach ($cards as $index => $card) {
            $kind = $card->kind->value;
            $kindOrder[$kind] ??= \count($kindOrder);

            if (!isset($factOrder[$card->factId])) {
                $factOrder[$card->factId] = $factsPerKind[$kind] ?? 0;
                $factsPerKind[$kind] = $factOrder[$card->factId] + 1;
            }
            $cardsPerFact[$card->factId] = ($cardsPerFact[$card->factId] ?? 0) + 1;

            $keys[$index] = [$cardsPerFact[$card->factId], $factOrder[$card->factId], $kindOrder[$kind]];
        }

        uksort($cards, static fn (int $a, int $b): int => $keys[$a] <=> $keys[$b]);

        return array_values($cards);
    }
}
