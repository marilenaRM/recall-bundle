<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Command;

use MarilenaRM\RecallBundle\Card\Card;
use MarilenaRM\RecallBundle\Card\DeckSynchronizer;
use MarilenaRM\RecallBundle\Fact\Fact;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'recall:sync',
    description: 'Extracts facts from the project and (re)generates the review cards of new or changed facts',
)]
final readonly class SyncCommand
{
    public function __construct(
        private DeckSynchronizer $synchronizer,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Show what would change without calling the model')]
        bool $dryRun = false,
    ): int {
        $report = $this->synchronizer->plan();

        $io->definitionList(
            ['Unchanged facts' => \count($report->unchanged)],
            ['Facts to generate' => \count($report->toGenerate)],
            ['Vanished facts' => \count($report->vanished)],
            ['Obsolete cards' => \count($report->obsolete)],
        );

        if ($dryRun) {
            $io->listing(array_map(
                static fn (Fact $fact): string => \sprintf('[%s] %s <comment>%s</comment>', $fact->kind->value, $fact->title, $fact->source),
                $report->toGenerate,
            ));

            return Command::SUCCESS;
        }

        $result = $this->synchronizer->apply($report, static function (Fact $fact, array $cards) use ($io): void {
            $io->writeln(\sprintf('  %s <info>%d card(s)</info>', $fact->title, \count($cards)));

            foreach ($cards as $card) {
                $io->writeln('    - '.$card->question);
            }
        });

        if ([] !== $result->duplicates) {
            $io->section(\sprintf('%d duplicate card(s) dropped', \count($result->duplicates)));
            $io->listing(array_map(static fn (Card $card): string => $card->question, $result->duplicates));
        }

        if ([] !== $result->failures) {
            $io->warning(array_map(
                static fn (string $factId, \Throwable $failure): string => \sprintf('%s: %s', $factId, $failure->getMessage()),
                array_keys($result->failures),
                $result->failures,
            ));
            $io->error(\sprintf('%d fact(s) could not be turned into cards; they keep their previous cards and will be retried.', \count($result->failures)));
        }

        if (null !== $result->deduplicationFailure) {
            $io->error('Deduplication failed, the new cards were all kept: '.$result->deduplicationFailure->getMessage());
        }

        if (!$result->isSuccessful()) {
            return Command::FAILURE;
        }

        $io->success('Deck synchronized.');

        return Command::SUCCESS;
    }
}
