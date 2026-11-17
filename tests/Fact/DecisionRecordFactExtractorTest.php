<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Tests\Fact;

use MarilenaRM\RecallBundle\Fact\DecisionRecordFactExtractor;
use MarilenaRM\RecallBundle\Fact\FactKind;
use MarilenaRM\RecallBundle\Fact\SourceLocator;
use PHPUnit\Framework\TestCase;

final class DecisionRecordFactExtractorTest extends TestCase
{
    public function testItReadsEachRecordTitledByItsFirstHeading(): void
    {
        $projectDir = \dirname(__DIR__, 2);
        $extractor = new DecisionRecordFactExtractor(
            [$projectDir.'/tests/Fixtures/docs/adr', $projectDir.'/does/not/exist'],
            $projectDir,
            new SourceLocator($projectDir, 6000),
        );

        $facts = iterator_to_array($extractor->extract(), false);

        self::assertCount(1, $facts);
        self::assertSame(FactKind::Decision, $facts[0]->kind);
        self::assertSame('Invoices are immutable once issued', $facts[0]->title);
        self::assertSame('tests/Fixtures/docs/adr/0001-invoices-are-immutable.md', $facts[0]->source);
        self::assertStringContainsString('Corrections go through a credit note.', $facts[0]->body);
    }

    public function testItYieldsNothingWhenNoDirectoryExists(): void
    {
        $extractor = new DecisionRecordFactExtractor(['/does/not/exist'], '/', new SourceLocator('/', 6000));

        self::assertSame([], iterator_to_array($extractor->extract(), false));
    }
}
