<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Tests\Card;

use MarilenaRM\RecallBundle\Card\Card;
use MarilenaRM\RecallBundle\Card\DuplicateFinder;
use MarilenaRM\RecallBundle\Fact\FactKind;
use MarilenaRM\RecallBundle\Tests\MockPlatform;
use PHPUnit\Framework\TestCase;

final class DuplicateFinderTest extends TestCase
{
    public function testOnlyIdsOfNewCardsAreKeptFromTheModelAnswer(): void
    {
        $prompt = '';
        $options = [];
        $platform = MockPlatform::create(static function (string $payload, array $callOptions) use (&$prompt, &$options): string {
            $prompt = $payload;
            $options = $callOptions;

            return json_encode(['ids' => ['new-2', 'existing', 'made-up']], \JSON_THROW_ON_ERROR);
        });

        $ids = (new DuplicateFinder($platform, 'mock-model', maxOutputTokens: 4000))->find(
            [self::card('existing', 'What does the second argument of #[IsGranted] on show() designate?')],
            [self::card('new-1', 'Why does logout() throw?'), self::card('new-2', 'What does the second argument of #[IsGranted] on edit() designate?')],
        );

        self::assertSame(['new-2'], $ids);
        self::assertSame(4000, $options['max_output_tokens'] ?? null);
        self::assertStringContainsString("EXISTING cards:\n[existing] Q: What does the second argument of #[IsGranted] on show() designate?", $prompt);
        self::assertStringContainsString("NEW cards:\n[new-1] Q: Why does logout() throw?", $prompt);
    }

    public function testTheModelIsNotCalledWithoutNewCards(): void
    {
        $platform = MockPlatform::create(static fn (): string => throw new \LogicException('The model should not be called.'));

        self::assertSame([], (new DuplicateFinder($platform, 'mock-model'))->find([self::card('existing', 'Q?')], []));
    }

    private static function card(string $id, string $question): Card
    {
        return new Card($id, 'route:'.$id, FactKind::Route, $question, 'Answer.', 'src/Controller.php:1');
    }
}
