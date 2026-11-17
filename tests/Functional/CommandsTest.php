<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Tests\Functional;

use MarilenaRM\RecallBundle\Card\DeckStorage;
use MarilenaRM\RecallBundle\Review\MemoryState;
use MarilenaRM\RecallBundle\Review\Progress;
use MarilenaRM\RecallBundle\Review\ProgressStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class CommandsTest extends TestCase
{
    private string $workDir;

    private Application $application;

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir().'/recall-functional-'.bin2hex(random_bytes(4));
        $kernel = new TestKernel($this->workDir);
        $this->application = new Application($kernel);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->workDir);
    }

    public function testADryRunListsTheFactsWithoutWritingTheDeck(): void
    {
        $tester = $this->execute('recall:sync', ['--dry-run' => true]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Route "checkout_confirm"', $tester->getDisplay());
        self::assertStringContainsString('Invoices are immutable once issued', $tester->getDisplay());
        self::assertFileDoesNotExist($this->workDir.'/deck.json');
    }

    public function testANewCardIsShownWithItsAnswerAndTestedTheNextDay(): void
    {
        $this->execute('recall:sync')->assertCommandIsSuccessful();

        $review = $this->execute('recall:review', inputs: ['']);

        $review->assertCommandIsSuccessful();
        self::assertStringContainsString('New · Decision', $review->getDisplay());
        self::assertStringContainsString('How is an issued invoice corrected?', $review->getDisplay());
        self::assertStringContainsString('Through a credit note, never by editing it.', $review->getDisplay());
        self::assertStringContainsString('tests/Fixtures/docs/adr/0001-invoices-are-immutable.md', $review->getDisplay());
        self::assertStringNotContainsString('How well did you remember?', $review->getDisplay());
        self::assertStringContainsString('1 new card(s) introduced, 0 card(s) reviewed.', $review->getDisplay());

        self::assertStringContainsString('Nothing to review today.', $this->execute('recall:review')->getDisplay());
    }

    public function testADueCardIsTestedAndRatedFromOneToFour(): void
    {
        $this->execute('recall:sync')->assertCommandIsSuccessful();
        $card = (new DeckStorage($this->workDir.'/deck.json'))->load()->cards()[0];
        $progress = new Progress();
        $progress->update($card, new MemoryState(3.0, 5.0, new \DateTimeImmutable('-4 days'), new \DateTimeImmutable('-1 day')));
        (new ProgressStorage($this->workDir.'/progress.json'))->save($progress);

        $review = $this->execute('recall:review', inputs: ['', '3']);

        $review->assertCommandIsSuccessful();
        self::assertStringContainsString('[1] Again', $review->getDisplay());
        self::assertStringContainsString('[4] Easy', $review->getDisplay());
        self::assertStringContainsString('0 new card(s) introduced, 1 card(s) reviewed.', $review->getDisplay());
        self::assertGreaterThan(
            new \DateTimeImmutable('+1 day'),
            (new ProgressStorage($this->workDir.'/progress.json'))->load()->stateOf($card)?->due,
        );
    }

    public function testAReviewRefusesANonInteractiveTerminal(): void
    {
        $tester = new CommandTester($this->application->find('recall:review'));

        self::assertSame(Command::INVALID, $tester->execute([], ['interactive' => false]));
    }

    /**
     * @param array<string, mixed> $arguments
     * @param list<string>         $inputs
     */
    private function execute(string $command, array $arguments = [], array $inputs = []): CommandTester
    {
        $tester = new CommandTester($this->application->find($command));
        $tester->setInputs($inputs);
        $tester->execute($arguments, ['interactive' => true]);

        return $tester;
    }
}
