<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Tests\Functional;

use MarilenaRM\RecallBundle\MarilenaRMRecallBundle;
use MarilenaRM\RecallBundle\Tests\Fixtures\App\Controller\CheckoutController;
use MarilenaRM\RecallBundle\Tests\MockPlatform;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(private readonly string $workDir)
    {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new MarilenaRMRecallBundle();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return $this->workDir.'/cache';
    }

    public function getLogDir(): string
    {
        return $this->workDir.'/log';
    }

    public static function platform(): PlatformInterface
    {
        return MockPlatform::create(static function (string $payload): string {
            if (str_contains($payload, 'NEW cards:')) {
                return '{"ids": []}';
            }

            return MockPlatform::answering(
                str_contains($payload, 'credit note')
                    ? [['How is an issued invoice corrected?', 'Through a credit note, never by editing it.']]
                    : [],
            );
        });
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', ['test' => true, 'http_method_override' => false]);
        $container->extension('marilena_rm_recall', [
            'model' => 'mock-model',
            'deck_path' => $this->workDir.'/deck.json',
            'progress_path' => $this->workDir.'/progress.json',
            'decision_paths' => ['%kernel.project_dir%/tests/Fixtures/docs/adr'],
        ]);
        $container->services()
            ->set(PlatformInterface::class)
            ->factory([self::class, 'platform']);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('checkout_confirm', '/checkout/{reference}/confirm')
            ->controller([CheckoutController::class, 'confirm'])
            ->methods(['POST']);
    }
}
