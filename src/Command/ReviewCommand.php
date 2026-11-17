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

        $introduced = $reviewed = 0;
        $ratings = array_combine(
            array_map(static fn (Rating $rating): int => $rating->value, Rating::cases()),
            array_map(static fn (Rating $rating): string => $rating->name, Rating::cases()),
        );

        foreach ($cards as $index => $card) {
            $state = $progress->stateOf($card);
            $io->section(\sprintf('%d/%d — %s%s', $index + 1, \count($cards), null === $state ? 'New · ' : '', $card->kind->name));
            $io->writeln($card->question);
            $io->newLine();

            if (null === $state) {
                $this->reveal($io, $card);
                $io->ask('Read it, then press Enter (first test tomorrow)', '');
                $state = $this->fsrs->introduce($this->clock->now());
                ++$introduced;
            } else {
                $io->ask('Your answer (optional, Enter to reveal)', '');
                $this->reveal($io, $card);
                $rating = Rating::fromName($io->choice('How well did you remember?', $ratings));
                $state = $this->fsrs->review($state, $rating, $this->clock->now());
                ++$reviewed;
            }

            $progress->update($card, $state);
            $this->progressStorage->save($progress);
            $io->writeln(\sprintf('<comment>Next review: %s</comment>', $state->due->format('Y-m-d')));
        }

        $io->success(\sprintf('%d new card(s) introduced, %d card(s) reviewed.', $introduced, $reviewed));

        return Command::SUCCESS;
    }

    private function reveal(SymfonyStyle $io, Card $card): void
    {
        $io->block($card->answer, 'ANSWER', 'fg=black;bg=green', ' ', true);
        $io->writeln('<comment>Source: '.$card->source.'</comment>');
    }
}
