<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Tests\Review;

use MarilenaRM\RecallBundle\Card\Card;
use MarilenaRM\RecallBundle\Fact\FactKind;
use MarilenaRM\RecallBundle\Review\MemoryState;
use MarilenaRM\RecallBundle\Review\Progress;
use MarilenaRM\RecallBundle\Review\ProgressStorage;
use PHPUnit\Framework\TestCase;

final class ProgressStorageTest extends TestCase
{
    public function testItRestoresTheSavedMemoryStates(): void
    {
        $path = sys_get_temp_dir().'/recall-progress-'.bin2hex(random_bytes(4)).'/progress.json';
        $card = new Card('abc', 'route:home', FactKind::Route, 'Q?', 'A.', 'src/Foo.php:1');
        $state = new MemoryState(3.0, 4.5, new \DateTimeImmutable('2026-11-09T09:00:00+00:00'), new \DateTimeImmutable('2026-11-12T09:00:00+00:00'));
        $progress = new Progress();
        $progress->update($card, $state);

        (new ProgressStorage($path))->save($progress);

        self::assertEquals($state, (new ProgressStorage($path))->load()->stateOf($card));
    }
}
