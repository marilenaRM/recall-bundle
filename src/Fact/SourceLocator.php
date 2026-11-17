<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Fact;

use Symfony\Component\Filesystem\Path;

/**
 * Reads the code behind a fact, so that the generated cards are grounded in it and
 * invalidated when it changes.
 */
final readonly class SourceLocator
{
    public function __construct(
        private string $projectDir,
        private int $maxLength,
    ) {
    }

    /**
     * @param \ReflectionClass<*>|\ReflectionMethod $reflection
     */
    public function reference(\ReflectionClass|\ReflectionMethod $reflection): string
    {
        $file = $reflection->getFileName();

        if (false === $file) {
            return $reflection->getName();
        }

        return \sprintf('%s:%d', Path::makeRelative($file, $this->projectDir), $reflection->getStartLine());
    }

    /**
     * @param \ReflectionClass<*>|\ReflectionMethod $reflection
     */
    public function isInProject(\ReflectionClass|\ReflectionMethod $reflection): bool
    {
        $file = $reflection->getFileName();

        return false !== $file
            && Path::isBasePath($this->projectDir, $file)
            && !Path::isBasePath($this->projectDir.'/vendor', $file);
    }

    /**
     * @param \ReflectionClass<*>|\ReflectionMethod $reflection
     */
    public function excerpt(\ReflectionClass|\ReflectionMethod $reflection): string
    {
        $file = $reflection->getFileName();

        if (false === $file || false === $lines = file($file)) {
            return '';
        }

        $start = $reflection->getStartLine() - 1;
        $docComment = $reflection->getDocComment();

        return $this->truncate(implode("\n", array_filter([
            false === $docComment ? null : $docComment,
            $this->attributes($reflection),
            implode('', \array_slice($lines, $start, $reflection->getEndLine() - $start)),
        ])));
    }

    /**
     * Reflection line numbers start at the declaration keyword, so attributes (routing, security,
     * mapping) would otherwise be missing from the excerpt.
     *
     * @param \ReflectionClass<*>|\ReflectionMethod $reflection
     */
    private function attributes(\ReflectionClass|\ReflectionMethod $reflection): ?string
    {
        $rendered = [];

        foreach ($reflection->getAttributes() as $attribute) {
            try {
                $arguments = $this->arguments($attribute->getArguments());
            } catch (\Error) {
                $arguments = '?';
            }

            $rendered[] = \sprintf('#[%s(%s)]', $attribute->getName(), $arguments);
        }

        return [] === $rendered ? null : implode("\n", $rendered);
    }

    /**
     * Rendered as PHP call arguments: a JSON list would read as a single array argument, which
     * changes the meaning of attributes such as #[IsGranted].
     *
     * @param array<int|string, mixed> $arguments
     */
    private function arguments(array $arguments): string
    {
        $rendered = [];

        foreach ($arguments as $name => $value) {
            $encoded = json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PARTIAL_OUTPUT_ON_ERROR);
            $rendered[] = \is_int($name) ? (string) $encoded : $name.': '.$encoded;
        }

        return implode(', ', $rendered);
    }

    public function truncate(string $text): string
    {
        return mb_strlen($text) > $this->maxLength ? mb_substr($text, 0, $this->maxLength)."\n[truncated]" : $text;
    }
}
