<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Tests\Review;

use MarilenaRM\RecallBundle\Card\Card;
use MarilenaRM\RecallBundle\Fact\FactKind;
use MarilenaRM\RecallBundle\Review\MemoryState;
use MarilenaRM\RecallBundle\Review\Progress;
use PHPUnit\Framework\TestCase;

final class ProgressTest extends TestCase
{
    public function testItServesOverdueCardsFirstThenALimitedNumberOfNewOnes(): void
    {
        $now = new \DateTimeImmutable('2026-11-09');
        $slightlyOverdue = self::card('slightly-overdue');
        $veryOverdue = self::card('very-overdue');
        $notYetDue = self::card('not-yet-due');
        $progress = new Progress([
            'slightly-overdue' => self::stateDueOn('2026-11-08'),
            'very-overdue' => self::stateDueOn('2026-11-01'),
            'not-yet-due' => self::stateDueOn('2026-11-20'),
        ]);

        $due = $progress->dueCards(
            [$slightlyOverdue, self::card('new-1'), $notYetDue, $veryOverdue, self::card('new-2')],
            $now,
            newLimit: 1,
        );

        self::assertSame(['very-overdue', 'slightly-overdue', 'new-1'], array_map(static fn (Card $card): string => $card->id, $due));
    }

    public function testACardDueLaterTodayIsAlreadyServed(): void
    {
        $card = self::card('due-tonight');
        $progress = new Progress(['due-tonight' => self::stateDueOn('2026-11-09 18:00')]);

        self::assertSame([$card], $progress->dueCards([$card], new \DateTimeImmutable('2026-11-09 09:00'), newLimit: 0));
        self::assertSame([], $progress->dueCards([$card], new \DateTimeImmutable('2026-11-08 23:00'), newLimit: 0));
    }

    public function testNewCardsAlternateFactsAndKindsBeforeAnySecondCardOfAFact(): void
    {
        $cards = [
            self::card('adr-a-1', 'decision:a', FactKind::Decision),
            self::card('adr-a-2', 'decision:a', FactKind::Decision),
            self::card('adr-b-1', 'decision:b', FactKind::Decision),
            self::card('adr-b-2', 'decision:b', FactKind::Decision),
            self::card('entity-c-1', 'entity:c', FactKind::Entity),
            self::card('route-d-1', 'route:d', FactKind::Route),
            self::card('route-d-2', 'route:d', FactKind::Route),
        ];

        $due = (new Progress())->dueCards($cards, new \DateTimeImmutable('2026-11-09'), newLimit: 10);

        self::assertSame(
            ['adr-a-1', 'entity-c-1', 'route-d-1', 'adr-b-1', 'adr-a-2', 'route-d-2', 'adr-b-2'],
            array_map(static fn (Card $card): string => $card->id, $due),
        );
    }

    private static function card(string $id, ?string $factId = null, FactKind $kind = FactKind::Route): Card
    {
        return new Card($id, $factId ?? 'route:'.$id, $kind, 'Q?', 'A.', 'src/Foo.php:1');
    }

    private static function stateDueOn(string $date): MemoryState
    {
        return new MemoryState(3.0, 5.0, new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable($date));
    }
}
