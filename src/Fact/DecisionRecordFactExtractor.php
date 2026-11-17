<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Fact;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

/**
 * Reads architecture decision records: the "why" a codebase cannot tell on its own.
 */
final readonly class DecisionRecordFactExtractor implements FactExtractorInterface
{
    /**
     * @param list<string> $paths
     */
    public function __construct(
        private array $paths,
        private string $projectDir,
        private SourceLocator $sourceLocator,
    ) {
    }

    public function extract(): iterable
    {
        $paths = array_values(array_filter($this->paths, is_dir(...)));

        if ([] === $paths) {
            return;
        }

        foreach ((new Finder())->files()->in($paths)->name('*.md')->notName('*template*')->sortByName() as $file) {
            $content = $file->getContents();
            $source = Path::makeRelative($file->getPathname(), $this->projectDir);

            yield new Fact(
                id: 'decision:'.$source,
                kind: FactKind::Decision,
                title: preg_match('/^#\s+(.+)$/m', $content, $matches) ? trim($matches[1]) : $file->getFilenameWithoutExtension(),
                body: $this->sourceLocator->truncate($content),
                source: $source,
            );
        }
    }
}
