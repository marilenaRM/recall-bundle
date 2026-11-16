<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Review;

/**
 * FSRS-5 scheduler with its published default parameters.
 *
 * @see https://github.com/open-spaced-repetition/fsrs4anki/wiki/The-Algorithm
 */
final readonly class Fsrs
{
    private const array W = [
        0.40255, 1.18385, 3.173, 15.69105, 7.1949, 0.5345, 1.4604, 0.0046, 1.54575, 0.1192,
        1.01925, 1.9395, 0.11, 0.29605, 2.2698, 0.2315, 2.9898, 0.51655, 0.6621,
    ];
    private const float DECAY = -0.5;
    private const float FACTOR = 19 / 81;
    private const int SECONDS_PER_DAY = 86400;

    public function __construct(
        private float $desiredRetention = 0.9,
        private int $maximumIntervalInDays = 365,
    ) {
    }

    public function review(?MemoryState $state, Rating $rating, \DateTimeImmutable $now): MemoryState
    {
        if (null === $state) {
            $stability = self::W[$rating->value - 1];
            $difficulty = $this->initialDifficulty($rating);
        } else {
            $elapsedDays = max(0, $now->getTimestamp() - $state->lastReview->getTimestamp()) / self::SECONDS_PER_DAY;
            $stability = $this->nextStability($state, $rating, $elapsedDays);
            $difficulty = $this->nextDifficulty($state->difficulty, $rating);
        }

        return new MemoryState(
            stability: $stability,
            difficulty: $difficulty,
            lastReview: $now,
            due: $now->modify(\sprintf('+%d days', $this->intervalInDays($stability))),
        );
    }

    public function retrievability(MemoryState $state, \DateTimeImmutable $now): float
    {
        $elapsedDays = max(0, $now->getTimestamp() - $state->lastReview->getTimestamp()) / self::SECONDS_PER_DAY;

        return (1 + self::FACTOR * $elapsedDays / $state->stability) ** self::DECAY;
    }

    private function nextStability(MemoryState $state, Rating $rating, float $elapsedDays): float
    {
        $stability = $state->stability;

        // Reviewed again the same day: memory has not decayed, only the short-term effect applies.
        if ($elapsedDays < 1) {
            return $stability * exp(self::W[17] * ($rating->value - 3 + self::W[18]));
        }

        $retrievability = (1 + self::FACTOR * $elapsedDays / $stability) ** self::DECAY;

        if (Rating::Again === $rating) {
            $forgotten = self::W[11]
                * $state->difficulty ** -self::W[12]
                * (($stability + 1) ** self::W[13] - 1)
                * exp(self::W[14] * (1 - $retrievability));

            return min($forgotten, $stability);
        }

        $growth = exp(self::W[8])
            * (11 - $state->difficulty)
            * $stability ** -self::W[9]
            * (exp(self::W[10] * (1 - $retrievability)) - 1)
            * (Rating::Hard === $rating ? self::W[15] : 1)
            * (Rating::Easy === $rating ? self::W[16] : 1);

        return $stability * ($growth + 1);
    }

    private function initialDifficulty(Rating $rating): float
    {
        return $this->clampDifficulty(self::W[4] - exp(self::W[5] * ($rating->value - 1)) + 1);
    }

    private function nextDifficulty(float $difficulty, Rating $rating): float
    {
        $delta = -self::W[6] * ($rating->value - 3);
        $damped = $difficulty + $delta * (10 - $difficulty) / 9;
        $reverted = self::W[7] * $this->initialDifficulty(Rating::Easy) + (1 - self::W[7]) * $damped;

        return $this->clampDifficulty($reverted);
    }

    private function intervalInDays(float $stability): int
    {
        $interval = $stability / self::FACTOR * ($this->desiredRetention ** (1 / self::DECAY) - 1);

        return (int) min(max(1, round($interval)), $this->maximumIntervalInDays);
    }

    private function clampDifficulty(float $difficulty): float
    {
        return min(max($difficulty, 1.0), 10.0);
    }
}
