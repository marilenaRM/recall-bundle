<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Fact;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

final readonly class RouteFactExtractor implements FactExtractorInterface
{
    public function __construct(
        private RouterInterface $router,
        private SourceLocator $sourceLocator,
    ) {
    }

    public function extract(): iterable
    {
        $seen = [];

        foreach ($this->router->getRouteCollection() as $name => $route) {
            // Framework and debug routes (_profiler, _wdt, _error...) say nothing about the project.
            if (str_starts_with($name, '_')) {
                continue;
            }

            // A localized route is expanded into one route per locale, all sharing one controller.
            $canonical = $route->getDefault('_canonical_route');
            $name = \is_string($canonical) ? $canonical : $name;

            if (isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;

            $controller = $this->reflectController($route);

            // Routes served by a bundle or intercepted by a firewall (login checks, live components)
            // carry no knowledge of the project itself.
            if (null === $controller || !$this->sourceLocator->isInProject($controller)) {
                continue;
            }

            yield new Fact(
                id: 'route:'.$name,
                kind: FactKind::Route,
                title: \sprintf('Route "%s" (%s)', $name, $route->getPath()),
                body: $this->describe($name, $route, $controller),
                source: $this->sourceLocator->reference($controller),
            );
        }
    }

    private function describe(string $name, Route $route, \ReflectionMethod $controller): string
    {
        $lines = [
            'Route name: '.$name,
            'Path: '.$route->getPath(),
            'Methods: '.([] === $route->getMethods() ? 'ANY' : implode(', ', $route->getMethods())),
        ];

        if ([] !== $route->getRequirements()) {
            $lines[] = 'Requirements: '.json_encode($route->getRequirements(), \JSON_UNESCAPED_SLASHES);
        }

        if ('' !== $route->getCondition()) {
            $lines[] = 'Condition: '.$route->getCondition();
        }

        $lines[] = 'Controller: '.$controller->class.'::'.$controller->name;
        $lines[] = "Controller source:\n".$this->sourceLocator->excerpt($controller);

        return implode("\n", $lines);
    }

    private function reflectController(Route $route): ?\ReflectionMethod
    {
        $controller = $route->getDefault('_controller');

        if (\is_array($controller) && \is_string($controller[0] ?? null) && \is_string($controller[1] ?? null)) {
            [$class, $method] = $controller;
        } elseif (\is_string($controller)) {
            [$class, $method] = str_contains($controller, '::') ? explode('::', $controller, 2) : [$controller, '__invoke'];
        } else {
            return null;
        }

        if (!method_exists($class, $method)) {
            return null;
        }

        return new \ReflectionMethod($class, $method);
    }
}
