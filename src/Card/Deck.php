<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Card;

use MarilenaRM\RecallBundle\Fact\Fact;

/**
 * The cards, and the fingerprint of the fact each group was written from. A fact that yielded no
 * card is still recorded, so it is not sent to the model again until its source changes.
 */
final class Deck
{
    /**
     * @param array<string, string> $fingerprints fact id => fingerprint
     * @param list<Card>            $cards
     */
    public function __construct(
        private array $fingerprints = [],
        private array $cards = [],
    ) {
    }

    public function fingerprintOf(string $factId): ?string
    {
        return $this->fingerprints[$factId] ?? null;
    }

    /**
     * @return list<string>
     */
    public function factIds(): array
    {
        return array_keys($this->fingerprints);
    }

    /**
     * @return list<Card>
     */
    public function cards(): array
    {
        return $this->cards;
    }

    /**
     * @return list<Card>
     */
    public function cardsOf(string $factId): array
    {
        return array_values(array_filter($this->cards, static fn (Card $card): bool => $card->factId === $factId));
    }

    /**
     * @param list<Card> $cards
     */
    public function record(Fact $fact, array $cards): void
    {
        $this->forget($fact->id);
        $this->fingerprints[$fact->id] = $fact->fingerprint;
        array_push($this->cards, ...$cards);
    }

    public function relocate(Fact $fact): void
    {
        $this->cards = array_map(
            static fn (Card $card): Card => $card->factId === $fact->id ? $card->withSource($fact->source) : $card,
            $this->cards,
        );
    }

    /**
     * @param list<string> $cardIds
     */
    public function drop(array $cardIds): void
    {
        $this->cards = array_values(array_filter($this->cards, static fn (Card $card): bool => !\in_array($card->id, $cardIds, true)));
    }

    public function forget(string $factId): void
    {
        unset($this->fingerprints[$factId]);
        $this->cards = array_values(array_filter($this->cards, static fn (Card $card): bool => $card->factId !== $factId));
    }
}
