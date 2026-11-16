<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use MarilenaRM\RecallBundle\Card\CardGenerator;
use MarilenaRM\RecallBundle\Card\DeckStorage;
use MarilenaRM\RecallBundle\Card\DeckSynchronizer;
use MarilenaRM\RecallBundle\Card\DuplicateFinder;
use MarilenaRM\RecallBundle\Command\ReviewCommand;
use MarilenaRM\RecallBundle\Command\SyncCommand;
use MarilenaRM\RecallBundle\Fact\DecisionRecordFactExtractor;
use MarilenaRM\RecallBundle\Fact\RouteFactExtractor;
use MarilenaRM\RecallBundle\Fact\SourceLocator;
use MarilenaRM\RecallBundle\Review\Fsrs;
use MarilenaRM\RecallBundle\Review\ProgressStorage;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('marilena_rm_recall.source_locator', SourceLocator::class)
            ->args([param('kernel.project_dir'), param('marilena_rm_recall.max_source_length')])

        ->set('marilena_rm_recall.extractor.route', RouteFactExtractor::class)
            ->args([service('router'), service('marilena_rm_recall.source_locator')])
            ->tag('marilena_rm_recall.fact_extractor', ['priority' => 20])

        ->set('marilena_rm_recall.extractor.decision', DecisionRecordFactExtractor::class)
            ->args([
                param('marilena_rm_recall.decision_paths'),
                param('kernel.project_dir'),
                service('marilena_rm_recall.source_locator'),
            ])
            ->tag('marilena_rm_recall.fact_extractor', ['priority' => 0])

        ->set('marilena_rm_recall.card_generator', CardGenerator::class)
            ->args([
                abstract_arg('Symfony AI platform, set from the bundle configuration'),
                param('marilena_rm_recall.model'),
                param('marilena_rm_recall.max_cards_per_fact'),
                param('marilena_rm_recall.language'),
                param('marilena_rm_recall.max_output_tokens'),
            ])

        ->set('marilena_rm_recall.duplicate_finder', DuplicateFinder::class)
            ->args([
                abstract_arg('Symfony AI platform, set from the bundle configuration'),
                param('marilena_rm_recall.model'),
                param('marilena_rm_recall.max_output_tokens'),
            ])

        ->set('marilena_rm_recall.deck_storage', DeckStorage::class)
            ->args([param('marilena_rm_recall.deck_path')])

        ->set('marilena_rm_recall.progress_storage', ProgressStorage::class)
            ->args([param('marilena_rm_recall.progress_path')])

        ->set('marilena_rm_recall.deck_synchronizer', DeckSynchronizer::class)
            ->args([
                tagged_iterator('marilena_rm_recall.fact_extractor'),
                service('marilena_rm_recall.deck_storage'),
                service('marilena_rm_recall.card_generator'),
                service('marilena_rm_recall.duplicate_finder'),
            ])

        ->set('marilena_rm_recall.fsrs', Fsrs::class)

        ->set('marilena_rm_recall.command.sync', SyncCommand::class)
            ->args([service('marilena_rm_recall.deck_synchronizer')])
            ->tag('console.command')

        ->set('marilena_rm_recall.command.review', ReviewCommand::class)
            ->args([
                service('marilena_rm_recall.deck_storage'),
                service('marilena_rm_recall.progress_storage'),
                service('marilena_rm_recall.fsrs'),
                service('clock'),
            ])
            ->tag('console.command');
};
