<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Tests\Fact;

use MarilenaRM\RecallBundle\Fact\Fact;
use MarilenaRM\RecallBundle\Fact\RouteFactExtractor;
use MarilenaRM\RecallBundle\Fact\SourceLocator;
use MarilenaRM\RecallBundle\Tests\Fixtures\App\Controller\CheckoutController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class RouteFactExtractorTest extends TestCase
{
    public function testItDescribesProjectRoutesWithTheirControllerSource(): void
    {
        $facts = $this->extract([
            'checkout_confirm' => new Route('/checkout/{reference}/confirm', ['_controller' => CheckoutController::class.'::confirm'], ['reference' => '[A-Z0-9]+'], methods: ['POST']),
            '_profiler' => new Route('/_profiler/{token}'),
        ]);

        self::assertCount(1, $facts);
        self::assertSame('route:checkout_confirm', $facts[0]->id);
        self::assertSame('tests/Fixtures/App/Controller/CheckoutController.php:16', $facts[0]->source);
        self::assertStringContainsString('Methods: POST', $facts[0]->body);
        self::assertStringContainsString('Requirements: {"reference":"[A-Z0-9]+"}', $facts[0]->body);
        self::assertStringContainsString('#[MarilenaRM\\RecallBundle\\Tests\\Fixtures\\App\\Attribute\\Audited("payments", "checkout", level: 2)]', $facts[0]->body);
        self::assertStringContainsString('Orders are confirmed only once the payment provider called back.', $facts[0]->body);
    }

    public function testALocalizedRouteIsOneFact(): void
    {
        $facts = $this->extract([
            'about.en' => new Route('/about', ['_controller' => CheckoutController::class.'::confirm', '_locale' => 'en', '_canonical_route' => 'about']),
            'about.fr' => new Route('/a-propos', ['_controller' => CheckoutController::class.'::confirm', '_locale' => 'fr', '_canonical_route' => 'about']),
        ]);

        self::assertSame(['route:about'], array_map(static fn (Fact $fact): string => $fact->id, $facts));
    }

    public function testARouteWithoutAResolvableControllerIsStillDescribed(): void
    {
        $facts = $this->extract(['legacy' => new Route('/legacy', ['_controller' => 'legacy.controller'])]);

        self::assertSame('legacy', $facts[0]->source);
        self::assertStringNotContainsString('Controller source', $facts[0]->body);
    }

    /**
     * @param array<string, Route> $routes
     *
     * @return list<Fact>
     */
    private function extract(array $routes): array
    {
        $collection = new RouteCollection();
        foreach ($routes as $name => $route) {
            $collection->add($name, $route);
        }

        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);

        return iterator_to_array((new RouteFactExtractor($router, new SourceLocator(\dirname(__DIR__, 2), 6000)))->extract(), false);
    }
}
