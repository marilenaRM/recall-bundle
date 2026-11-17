<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Review;

enum Rating: int
{
    case Again = 1;
    case Hard = 2;
    case Good = 3;
    case Easy = 4;

    public static function fromName(mixed $name): self
    {
        foreach (self::cases() as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }

        throw new \ValueError(\sprintf('"%s" is not a rating.', get_debug_type($name)));
    }
}
