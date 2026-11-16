<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Fact;

enum FactKind: string
{
    case Route = 'route';
    case Entity = 'entity';
    case Decision = 'decision';
}
