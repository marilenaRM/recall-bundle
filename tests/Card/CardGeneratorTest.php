<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Tests\Card;

use MarilenaRM\RecallBundle\Card\Card;
use MarilenaRM\RecallBundle\Card\CardGenerator;
use MarilenaRM\RecallBundle\Fact\Fact;
use MarilenaRM\RecallBundle\Fact\FactKind;
use MarilenaRM\RecallBundle\Tests\MockPlatform;
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
}
