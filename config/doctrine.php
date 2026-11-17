<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use MarilenaRM\RecallBundle\Fact\DoctrineEntityFactExtractor;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('marilena_rm_recall.extractor.doctrine_entity', DoctrineEntityFactExtractor::class)
            ->args([service('doctrine'), service('marilena_rm_recall.source_locator')])
            ->tag('marilena_rm_recall.fact_extractor', ['priority' => 10]);
};
