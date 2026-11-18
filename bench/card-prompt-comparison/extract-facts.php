<?php

declare(strict_types=1);

/*
 * Extracts the comparison's facts with the bundle's own extractors, from the test fixtures
 * (ADRs, Doctrine entities, the checkout route) of the given checkout.
 *
 *   php extract-facts.php <checkout> <facts.json>
 */

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\AbstractManagerRegistry;
use MarilenaRM\RecallBundle\Fact\DecisionRecordFactExtractor;
use MarilenaRM\RecallBundle\Fact\DoctrineEntityFactExtractor;
use MarilenaRM\RecallBundle\Fact\Fact;
use MarilenaRM\RecallBundle\Fact\RouteFactExtractor;
use MarilenaRM\RecallBundle\Fact\SourceLocator;
use MarilenaRM\RecallBundle\Tests\Fixtures\App\Controller\CheckoutController;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

[, $checkout, $output] = $argv + [null, null, null];
if (null === $checkout || null === $output) {
    fwrite(\STDERR, "Usage: php extract-facts.php <checkout> <facts.json>\n");
    exit(1);
}
$checkout = realpath($checkout) ?: $checkout;
require $checkout.'/vendor/autoload.php';

$locator = new SourceLocator($checkout, 6000);

// Routes: the same route the bundle's tests use.
$routes = new RouteCollection();
$routes->add('checkout_confirm', new Route(
    '/checkout/{reference}/confirm',
    ['_controller' => CheckoutController::class.'::confirm'],
    ['reference' => '[A-Z0-9]+'],
    methods: ['POST'],
));
$router = new class($routes) implements RouterInterface {
    private RequestContext $context;

    public function __construct(private RouteCollection $routes)
    {
        $this->context = new RequestContext();
    }

    public function getRouteCollection(): RouteCollection
    {
        return $this->routes;
    }

    public function setContext(RequestContext $context): void
    {
        $this->context = $context;
    }

    public function getContext(): RequestContext
    {
        return $this->context;
    }

    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        throw new LogicException('Not needed by the extractor.');
    }

    public function match(string $pathinfo): array
    {
        throw new LogicException('Not needed by the extractor.');
    }
};

// Entities: the fixture entities, mapped like the bundle's tests do.
$config = ORMSetup::createAttributeMetadataConfig([$checkout.'/tests/Fixtures/App/Entity'], isDevMode: true);
if (\PHP_VERSION_ID >= 80400) {
    $config->enableNativeLazyObjects(true);
} else {
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('CardPromptComparisonProxies');
}
$entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
$registry = new class($entityManager) extends AbstractManagerRegistry {
    public function __construct(private EntityManager $entityManager)
    {
        parent::__construct('orm', ['default' => 'connection'], ['default' => 'manager'], 'default', 'default', 'Doctrine\Persistence\Proxy');
    }

    protected function getService(string $name): object
    {
        return 'manager' === $name ? $this->entityManager : $this->entityManager->getConnection();
    }

    protected function resetService(string $name): void
    {
    }
};

$extractors = [
    new DecisionRecordFactExtractor([$checkout.'/tests/Fixtures/docs/adr'], $checkout, $locator),
    new DoctrineEntityFactExtractor($registry, $locator),
    new RouteFactExtractor($router, $locator),
];

$facts = [];
foreach ($extractors as $extractor) {
    foreach ($extractor->extract() as $fact) {
        \assert($fact instanceof Fact);
        $facts[] = [
            'id' => $fact->id,
            'kind' => $fact->kind->value,
            'title' => $fact->title,
            'body' => $fact->body,
            'source' => $fact->source,
        ];
    }
}

file_put_contents($output, json_encode($facts, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n");
printf("%d facts written to %s:\n", \count($facts), $output);
foreach ($facts as $fact) {
    printf("  - %s\n", $fact['id']);
}
