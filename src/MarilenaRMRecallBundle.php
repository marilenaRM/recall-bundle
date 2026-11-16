<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle;

use MarilenaRM\RecallBundle\Fact\FactExtractorInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class MarilenaRMRecallBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('platform')
                    ->info('Service id of the Symfony AI platform that writes the cards.')
                    ->defaultValue(PlatformInterface::class)
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('model')
                    ->info('Model name, as known to the platform\'s model catalog.')
                    ->isRequired()
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('language')
                    ->info('Language the cards are written in.')
                    ->defaultValue('English')
                    ->cannotBeEmpty()
                ->end()
                ->integerNode('max_cards_per_fact')
                    ->defaultValue(2)
                    ->min(1)
                    ->max(3)
                ->end()
                ->integerNode('max_output_tokens')
                    ->info('Output budget of each model call, thinking included: the Anthropic bridge defaults to 1000, which cuts structured output.')
                    ->defaultValue(8192)
                    ->min(256)
                ->end()
                ->integerNode('max_source_length')
                    ->info('Characters of source code or document sent to the model per fact.')
                    ->defaultValue(6000)
                    ->min(500)
                ->end()
                ->scalarNode('deck_path')
                    ->info('Shared deck, meant to be committed.')
                    ->defaultValue('%kernel.project_dir%/recall/deck.json')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('progress_path')
                    ->info('Personal review progress, meant to stay out of version control.')
                    ->defaultValue('%kernel.project_dir%/var/recall/progress.json')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('decision_paths')
                    ->info('Directories holding architecture decision records, as Markdown files.')
                    ->scalarPrototype()->end()
                    ->defaultValue(['%kernel.project_dir%/docs/adr', '%kernel.project_dir%/doc/adr'])
                ->end()
            ->end();
    }

    /**
     * @param array{platform: string, model: string, language: string, max_cards_per_fact: int, max_output_tokens: int, max_source_length: int, deck_path: string, progress_path: string, decision_paths: list<string>} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $configurator->parameters()
            ->set('marilena_rm_recall.model', $config['model'])
            ->set('marilena_rm_recall.language', $config['language'])
            ->set('marilena_rm_recall.max_cards_per_fact', $config['max_cards_per_fact'])
            ->set('marilena_rm_recall.max_output_tokens', $config['max_output_tokens'])
            ->set('marilena_rm_recall.max_source_length', $config['max_source_length'])
            ->set('marilena_rm_recall.deck_path', $config['deck_path'])
            ->set('marilena_rm_recall.progress_path', $config['progress_path'])
            ->set('marilena_rm_recall.decision_paths', $config['decision_paths']);

        $configurator->import('../config/services.php');
        $services = $configurator->services();
        $services->get('marilena_rm_recall.card_generator')->arg(0, service($config['platform']));
        $services->get('marilena_rm_recall.duplicate_finder')->arg(0, service($config['platform']));

        if (isset($container->getParameter('kernel.bundles')['DoctrineBundle'])) {
            $configurator->import('../config/doctrine.php');
        }

        $container->registerForAutoconfiguration(FactExtractorInterface::class)
            ->addTag('marilena_rm_recall.fact_extractor');
    }
}
