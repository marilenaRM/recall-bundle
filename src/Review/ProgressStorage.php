<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Review;

use MarilenaRM\RecallBundle\Storage\Json;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Progress is personal: it lives in a git-ignored location, one per developer checkout.
 */
final readonly class ProgressStorage
{
    public function __construct(
        private string $path,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function load(): Progress
    {
        if (!is_file($this->path)) {
            return new Progress();
        }

        $data = Json::decodeFile($this->filesystem, $this->path);
        $states = [];
        foreach (array_keys($data) as $cardId) {
            $states[(string) $cardId] = MemoryState::fromArray(Json::array($data, (string) $cardId));
        }

        return new Progress($states);
    }

    public function save(Progress $progress): void
    {
        $this->filesystem->dumpFile($this->path, json_encode(
            array_map(static fn (MemoryState $state): array => $state->toArray(), $progress->states()),
            \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR,
        )."\n");
    }
}
