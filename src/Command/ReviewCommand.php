<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Command;

use MarilenaRM\RecallBundle\Card\Card;
use MarilenaRM\RecallBundle\Card\DeckStorage;
use MarilenaRM\RecallBundle\Review\Fsrs;
use MarilenaRM\RecallBundle\Review\ProgressStorage;
use MarilenaRM\RecallBundle\Review\Rating;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'recall:review',
    description: 'Reviews the cards due today',
)]
final readonly class ReviewCommand
{
    public function __construct(
        private DeckStorage $deckStorage,
        private ProgressStorage $progressStorage,
        private Fsrs $fsrs,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        InputInterface $input,
        #[Option(description: 'Maximum number of never-reviewed cards to introduce')]
        int $new = 5,
    ): int {
        if (!$input->isInteractive()) {
            $io->error('A review session needs an interactive terminal.');

            return Command::INVALID;
        }

        $progress = $this->progressStorage->load();
        $cards = $progress->dueCards($this->deckStorage->load()->cards(), $this->clock->now(), $new);

        if ([] === $cards) {
            $io->success('Nothing to review today.');

            return Command::SUCCESS;
        }

        foreach ($cards as $index => $card) {
            $this->ask($io, $card, $index + 1, \count($cards));

            $rating = Rating::fromName($io->choice('How well did you remember?', array_column(Rating::cases(), 'name'), Rating::Good->name));
            $state = $this->fsrs->review($progress->stateOf($card), $rating, $this->clock->now());
            $progress->update($card, $state);
            $this->progressStorage->save($progress);

            $io->writeln(\sprintf('<comment>Next review: %s</comment>', $state->due->format('Y-m-d')));
        }

        $io->success(\sprintf('%d card(s) reviewed.', \count($cards)));

        return Command::SUCCESS;
    }

    private function ask(SymfonyStyle $io, Card $card, int $position, int $total): void
    {
        $io->section(\sprintf('%d/%d — %s', $position, $total, $card->kind->name));
        $io->writeln($card->question);
        $io->newLine();
        $io->ask('Your answer (optional, Enter to reveal)', '');
        $io->block($card->answer, 'ANSWER', 'fg=black;bg=green', ' ', true);
        $io->writeln('<comment>Source: '.$card->source.'</comment>');
    }
}
