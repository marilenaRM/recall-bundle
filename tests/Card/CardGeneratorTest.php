<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Tests\Card;

use MarilenaRM\RecallBundle\Card\Card;
use MarilenaRM\RecallBundle\Card\CardGenerator;
use MarilenaRM\RecallBundle\Fact\Fact;
use MarilenaRM\RecallBundle\Fact\FactKind;
use MarilenaRM\RecallBundle\Tests\MockPlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CardGeneratorTest extends TestCase
{
    public function testItTurnsTheModelAnswerIntoCardsPointingAtTheFactSource(): void
    {
        $prompt = '';
        $options = [];
        $platform = MockPlatform::create(static function (string $payload, array $callOptions) use (&$prompt, &$options): string {
            $prompt = $payload;
            $options = $callOptions;

            return MockPlatform::answering([
                ['Why is an issued invoice never edited?', 'Accounting law forbids it.'],
                ['How is an issued invoice corrected?', 'Through a credit note.'],
                ['A third card', 'over the configured maximum'],
            ]);
        });
        $fact = new Fact('decision:docs/adr/0001.md', FactKind::Decision, 'Invoices are immutable', 'An issued invoice is never updated.', 'docs/adr/0001.md');

        $cards = (new CardGenerator($platform, 'mock-model', maxCardsPerFact: 2, language: 'English', maxOutputTokens: 4000))->generate($fact);

        self::assertSame(
            ['Why is an issued invoice never edited?', 'How is an issued invoice corrected?'],
            array_map(static fn (Card $card): string => $card->question, $cards),
        );
        self::assertSame('docs/adr/0001.md', $cards[0]->source);
        self::assertSame(4000, $options['max_output_tokens'] ?? null);
        self::assertSame('decision:docs/adr/0001.md', $cards[0]->factId);
        self::assertStringContainsString('An issued invoice is never updated.', $prompt);
    }

    /**
     * Guards the rules that answer defects seen on real decks, so that rewording the prompt cannot drop one silently.
     */
    #[DataProvider('provideRules')]
    public function testThePromptKeepsTheRuleAgainst(string $rule): void
    {
        $prompt = '';
        $platform = MockPlatform::create(static function (string $payload) use (&$prompt): string {
            $prompt = $payload;

            return MockPlatform::answering([]);
        });
        $fact = new Fact('decision:docs/adr/0001.md', FactKind::Decision, 'Invoices are immutable', 'An issued invoice is never updated.', 'docs/adr/0001.md');

        (new CardGenerator($platform, 'mock-model', maxCardsPerFact: 2, language: 'English'))->generate($fact);

        self::assertStringContainsString($rule, $prompt);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideRules(): iterable
    {
        yield 'two questions in one card' => ['One question per card, asking for one thing'];
        yield 'a question that also asks why' => ['"What does X do and why?"'];
        yield 'internal labels of the material' => ['Never mention its internal labels'];
        yield 'labels in answers' => ['applies to answers as much as to questions'];
        yield 'a generic closing formula' => ['do not end questions with a generic tail'];
        yield 'plumbing questions' => ['Skip plumbing'];
    }
}
