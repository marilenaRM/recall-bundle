<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Tests\Fixtures\App\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class Audited
{
    public function __construct(
        public string $channel,
        public string $subject = '',
        public int $level = 0,
    ) {
    }
}
