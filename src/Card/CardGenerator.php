<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Card;

use MarilenaRM\RecallBundle\Fact\Fact;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;

final readonly class CardGenerator
{
    private const string INSTRUCTIONS = <<<'PROMPT'
        You write spaced-repetition review cards that help developers remember a Symfony project they work on.

        Rules:
        - Ask about intent, behavior and consequences: why the code is shaped this way, what happens when it runs,
          which non-obvious rule it enforces. Never ask for a literal value a developer would look up anyway
          (exact paths, field lists, type names).
        - One question per card, asking for one thing. Never join two asks, whether by "and", "or" or a comma:
          "What does X do and why?", "How and when does Y run?" and "Why X, and what happens if not?" each ask
          two things. Keep the ask that matters most, or write two cards. Before returning, reread every
          question: if a complete answer needs two separate points, trim or split it.
        - Cards are reviewed without the material at hand. Never mention its internal labels: option or
          proposition numbers and letters, ticket, incident or ADR numbers, section numbers, "the document",
          "the decision above". Name the concept itself: "storing sessions in Redis", not "option 2". This
          applies to answers as much as to questions.
        - Phrase each question around the specific code it is about. Do not reuse wording from these rules, and
          do not end questions with a generic tail such as "and what should one know before changing it?".
        - Skip plumbing: what a controller passes to its template, which variables a page renders, how it copes
          with a missing record or an empty table. Ask about such handling only when it enforces a deliberate
          security or business rule.
        - Ground every answer strictly in the provided material. If the material does not say it, do not ask it.
        - Answers fit in three sentences.
        - Return at most %d cards, and none when the material is boilerplate with nothing worth remembering.
        - Write the questions and answers in %s.
        PROMPT;

    /**
     * @param non-empty-string $model
     */
    public function __construct(
        private PlatformInterface $platform,
        private string $model,
        private int $maxCardsPerFact,
        private string $language,
        private int $maxOutputTokens = 8192,
    ) {
    }

    /**
     * @return list<Card>
     */
    public function generate(Fact $fact): array
    {
        $messages = new MessageBag(
            Message::forSystem(\sprintf(self::INSTRUCTIONS, $this->maxCardsPerFact, $this->language)),
            Message::ofUser(\sprintf("%s\nSource: %s\n\n%s", $fact->title, $fact->source, $fact->body)),
        );

        $drafts = $this->platform
            ->invoke($this->model, $messages, [
                PlatformSubscriber::RESPONSE_FORMAT => CardDrafts::class,
                PlatformOptions::MAX_OUTPUT_TOKENS => $this->maxOutputTokens,
            ])
            ->asObject(CardDrafts::class);

        return array_map(
            static fn (CardDraft $draft): Card => Card::fromDraft($fact, $draft),
            \array_slice($drafts->cards, 0, $this->maxCardsPerFact),
        );
    }
}
