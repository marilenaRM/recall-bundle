<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Review;

use MarilenaRM\RecallBundle\Storage\Json;

final readonly class MemoryState
{
    public function __construct(
        public float $stability,
        public float $difficulty,
        public \DateTimeImmutable $lastReview,
        public \DateTimeImmutable $due,
    ) {
    }

    public function isDue(\DateTimeImmutable $now): bool
    {
        return $this->due <= $now;
    }

    /**
     * @return array{stability: float, difficulty: float, last_review: string, due: string}
     */
    public function toArray(): array
    {
        return [
            'stability' => $this->stability,
            'difficulty' => $this->difficulty,
            'last_review' => $this->lastReview->format(\DateTimeInterface::ATOM),
            'due' => $this->due->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            stability: Json::float($data, 'stability'),
            difficulty: Json::float($data, 'difficulty'),
            lastReview: new \DateTimeImmutable(Json::string($data, 'last_review')),
            due: new \DateTimeImmutable(Json::string($data, 'due')),
        );
    }
}
