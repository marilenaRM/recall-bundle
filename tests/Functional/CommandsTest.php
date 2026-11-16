<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Tests\Functional;

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

    public function testTheCardsWrittenBySyncAreServedInTheReview(): void
    {
        $this->execute('recall:sync')->assertCommandIsSuccessful();

        $review = $this->execute('recall:review', inputs: ['', 'Good']);

        $review->assertCommandIsSuccessful();
        self::assertStringContainsString('How is an issued invoice corrected?', $review->getDisplay());
        self::assertStringContainsString('Through a credit note, never by editing it.', $review->getDisplay());
        self::assertStringContainsString('tests/Fixtures/docs/adr/0001-invoices-are-immutable.md', $review->getDisplay());
        self::assertStringContainsString('1 card(s) reviewed.', $review->getDisplay());

        self::assertStringContainsString('Nothing to review today.', $this->execute('recall:review')->getDisplay());
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
