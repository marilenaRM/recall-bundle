<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Tests\Fact;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\ManagerRegistry;
use MarilenaRM\RecallBundle\Fact\DoctrineEntityFactExtractor;
use MarilenaRM\RecallBundle\Fact\Fact;
use MarilenaRM\RecallBundle\Fact\SourceLocator;
use MarilenaRM\RecallBundle\Tests\Fixtures\App\Entity\Invoice;
use PHPUnit\Framework\TestCase;

final class DoctrineEntityFactExtractorTest extends TestCase
{
    public function testItDescribesEachEntityFromItsMappingAndSource(): void
    {
        $projectDir = \dirname(__DIR__, 2);
        $config = ORMSetup::createAttributeMetadataConfig([$projectDir.'/tests/Fixtures/App/Entity'], isDevMode: true);
        $config->setProxyDir(sys_get_temp_dir());
        $config->setProxyNamespace('RecallTestProxies');
        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagers')->willReturn(['default' => $entityManager]);

        $facts = iterator_to_array((new DoctrineEntityFactExtractor($registry, new SourceLocator($projectDir, 6000)))->extract(), false);
        $invoice = array_values(array_filter($facts, static fn (Fact $fact): bool => 'entity:'.Invoice::class === $fact->id))[0];

        self::assertCount(2, $facts);
        self::assertSame('tests/Fixtures/App/Entity/Invoice.php:10', $invoice->source);
        self::assertStringContainsString('Fields: id (integer), number (string)', $invoice->body);
        self::assertStringContainsString('customer -> '.\MarilenaRM\RecallBundle\Tests\Fixtures\App\Entity\Customer::class, $invoice->body);
        self::assertStringContainsString('public function describe(): string', $invoice->body);
        self::assertStringContainsString('#[Doctrine\\ORM\\Mapping\\Entity(', $invoice->body);
    }
}
