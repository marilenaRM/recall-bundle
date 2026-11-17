<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Tests\Review;

use MarilenaRM\RecallBundle\Review\Fsrs;
use MarilenaRM\RecallBundle\Review\MemoryState;
use MarilenaRM\RecallBundle\Review\Rating;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FsrsTest extends TestCase
{
    private const string NOW = '2026-11-09 09:00:00';

    #[DataProvider('firstReviews')]
    public function testAFirstReviewUsesTheInitialStabilityOfItsRating(Rating $rating, float $stability, string $due): void
    {
        $state = (new Fsrs())->review(null, $rating, new \DateTimeImmutable(self::NOW));

        self::assertEqualsWithDelta($stability, $state->stability, 0.00001);
        self::assertSame($due, $state->due->format('Y-m-d'));
    }

    /**
     * @return iterable<string, array{Rating, float, string}>
     */
    public static function firstReviews(): iterable
    {
        yield 'forgotten, back tomorrow' => [Rating::Again, 0.40255, '2026-11-10'];
        yield 'good' => [Rating::Good, 3.173, '2026-11-12'];
        yield 'easy' => [Rating::Easy, 15.69105, '2026-11-25'];
    }

    public function testANewCardIsIntroducedForATestTheNextDay(): void
    {
        $fsrs = new Fsrs();
        $introduced = $fsrs->introduce(new \DateTimeImmutable(self::NOW));

        self::assertSame('2026-11-10 09:00:00', $introduced->due->format('Y-m-d H:i:s'));
        self::assertGreaterThan(
            $introduced->stability,
            $fsrs->review($introduced, Rating::Good, $introduced->due)->stability,
        );
    }

    public function testDifficultyStartsHigherForHarderRatings(): void
    {
        $fsrs = new Fsrs();
        $now = new \DateTimeImmutable(self::NOW);

        self::assertGreaterThan(
            $fsrs->review(null, Rating::Easy, $now)->difficulty,
            $fsrs->review(null, Rating::Again, $now)->difficulty,
        );
    }

    public function testRememberingOnTimeGrowsTheIntervalAndForgettingShrinksIt(): void
    {
        $fsrs = new Fsrs();
        $state = new MemoryState(10.0, 5.0, new \DateTimeImmutable('2026-10-30 09:00:00'), new \DateTimeImmutable(self::NOW));
        $now = new \DateTimeImmutable(self::NOW);

        $remembered = $fsrs->review($state, Rating::Good, $now);
        $forgotten = $fsrs->review($state, Rating::Again, $now);

        self::assertGreaterThan(10.0, $remembered->stability);
        self::assertLessThan(10.0, $forgotten->stability);
        self::assertGreaterThan(5.0, $forgotten->difficulty);
        self::assertGreaterThan($remembered->due, (new Fsrs())->review($state, Rating::Easy, $now)->due);
    }

    public function testRetrievabilityMatchesTheDesiredRetentionWhenTheCardFallsDue(): void
    {
        $fsrs = new Fsrs();
        $state = $fsrs->review(null, Rating::Easy, new \DateTimeImmutable(self::NOW));

        self::assertEqualsWithDelta(0.9, $fsrs->retrievability($state, $state->due), 0.01);
    }

    public function testIntervalsAreCappedAtTheMaximum(): void
    {
        $state = new MemoryState(3000.0, 1.0, new \DateTimeImmutable('2025-11-09'), new \DateTimeImmutable(self::NOW));

        $next = (new Fsrs(maximumIntervalInDays: 30))->review($state, Rating::Easy, new \DateTimeImmutable(self::NOW));

        self::assertSame('2026-12-09', $next->due->format('Y-m-d'));
    }
}
