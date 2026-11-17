<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Card;

use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;

/**
 * Cards are written one fact at a time, so the same rule shows up on several facts: the second
 * argument of #[IsGranted] on every protected route, a non-nullable owner on every entity.
 */
final readonly class DuplicateFinder
{
    private const string INSTRUCTIONS = <<<'PROMPT'
        You review the cards of a spaced-repetition deck about a Symfony project.

        A NEW card is a duplicate when an EXISTING card, or a NEW card listed before it, already tests the same
        knowledge: the same rule, mechanism or decision, even when it is worded differently or applies it to
        another class or route. Cards that only share a topic are not duplicates.

        Return the ids of the duplicate NEW cards. Never return the id of an EXISTING card, and keep the first
        NEW card of each group of duplicates.
        PROMPT;

    /**
     * @param non-empty-string $model
     */
    public function __construct(
        private PlatformInterface $platform,
        private string $model,
        private int $maxOutputTokens = 8192,
    ) {
    }

    /**
     * @param list<Card> $existing   cards already in the deck, never reported
     * @param list<Card> $candidates cards just generated, in generation order
     *
     * @return list<string> ids of the candidates to drop
     */
    public function find(array $existing, array $candidates): array
    {
        if ([] === $candidates) {
            return [];
        }

        $messages = new MessageBag(
            Message::forSystem(self::INSTRUCTIONS),
            Message::ofUser(\sprintf("EXISTING cards:\n%s\n\nNEW cards:\n%s", $this->list($existing), $this->list($candidates))),
        );

        $duplicates = $this->platform
            ->invoke($this->model, $messages, [
                PlatformSubscriber::RESPONSE_FORMAT => Duplicates::class,
                PlatformOptions::MAX_OUTPUT_TOKENS => $this->maxOutputTokens,
            ])
            ->asObject(Duplicates::class);

        // The model only proposes: an id it made up, or one of an existing card, is ignored.
        $candidateIds = array_map(static fn (Card $card): string => $card->id, $candidates);

        return array_values(array_intersect($candidateIds, $duplicates->ids));
    }

    /**
     * @param list<Card> $cards
     */
    private function list(array $cards): string
    {
        if ([] === $cards) {
            return '(none)';
        }

        return implode("\n", array_map(
            static fn (Card $card): string => \sprintf('[%s] Q: %s A: %s', $card->id, $card->question, $card->answer),
            $cards,
        ));
    }
}
