<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Card;

use Symfony\AI\Platform\Exception\ExceptionInterface;

final readonly class SyncResult
{
    /**
     * @param array<string, ExceptionInterface> $failures               generation failures, by fact id
     * @param list<Card>                        $duplicates             generated cards dropped as duplicates
     * @param ExceptionInterface|null           $deduplicationFailure   the new cards are kept when deduplication fails
     */
    public function __construct(
        public array $failures = [],
        public array $duplicates = [],
        public ?ExceptionInterface $deduplicationFailure = null,
    ) {
    }

    public function isSuccessful(): bool
    {
        return [] === $this->failures && null === $this->deduplicationFailure;
    }
}
