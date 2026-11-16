<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Card;

use MarilenaRM\RecallBundle\Storage\Json;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The deck file is meant to be committed: every developer reviews the same cards.
 */
final readonly class DeckStorage
{
    public function __construct(
        private string $path,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function load(): Deck
    {
        if (!is_file($this->path)) {
            return new Deck();
        }

        $data = Json::decodeFile($this->filesystem, $this->path);
        $facts = Json::array($data, 'facts');
        $fingerprints = [];
        foreach (array_keys($facts) as $factId) {
            $fingerprints[(string) $factId] = Json::string($facts, (string) $factId);
        }

        return new Deck($fingerprints, array_map(Card::fromArray(...), Json::objects($data, 'cards')));
    }

    public function save(Deck $deck): void
    {
        $fingerprints = [];
        foreach ($deck->factIds() as $factId) {
            $fingerprints[$factId] = $deck->fingerprintOf($factId);
        }
        ksort($fingerprints);

        $cards = $deck->cards();
        usort($cards, static fn (Card $a, Card $b): int => [$a->factId, $a->id] <=> [$b->factId, $b->id]);

        $this->filesystem->dumpFile($this->path, json_encode(
            ['facts' => (object) $fingerprints, 'cards' => array_map(static fn (Card $card): array => $card->toArray(), $cards)],
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR,
        )."\n");
    }
}
