<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Fact;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;

final readonly class DoctrineEntityFactExtractor implements FactExtractorInterface
{
    public function __construct(
        private ManagerRegistry $registry,
        private SourceLocator $sourceLocator,
    ) {
    }

    public function extract(): iterable
    {
        foreach ($this->registry->getManagers() as $manager) {
            foreach ($manager->getMetadataFactory()->getAllMetadata() as $metadata) {
                $class = $metadata->getReflectionClass();

                yield new Fact(
                    id: 'entity:'.$metadata->getName(),
                    kind: FactKind::Entity,
                    title: 'Entity '.$class->getShortName(),
                    body: $this->describe($metadata, $class),
                    source: $this->sourceLocator->reference($class),
                );
            }
        }
    }

    /**
     * @param ClassMetadata<object>     $metadata
     * @param \ReflectionClass<*>     $class
     */
    private function describe(ClassMetadata $metadata, \ReflectionClass $class): string
    {
        $fields = array_map(
            static fn (string $field): string => \sprintf('%s (%s)', $field, $metadata->getTypeOfField($field) ?? 'unknown'),
            $metadata->getFieldNames(),
        );
        $associations = array_map(
            static fn (string $association): string => \sprintf('%s -> %s', $association, $metadata->getAssociationTargetClass($association) ?? 'unknown'),
            $metadata->getAssociationNames(),
        );

        return implode("\n", [
            'Entity: '.$metadata->getName(),
            'Fields: '.implode(', ', $fields),
            'Associations: '.([] === $associations ? 'none' : implode(', ', $associations)),
            "Source:\n".$this->sourceLocator->excerpt($class),
        ]);
    }
}
