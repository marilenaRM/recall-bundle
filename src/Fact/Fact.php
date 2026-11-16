<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Fact;

final readonly class Fact
{
    public string $fingerprint;

    public function __construct(
        public string $id,
        public FactKind $kind,
        public string $title,
        public string $body,
        public string $source,
    ) {
        $this->fingerprint = hash('xxh128', $body);
    }
}
