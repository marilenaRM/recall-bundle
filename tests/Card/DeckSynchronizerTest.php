<?php

declare(strict_types=1);

namespace MarilenaRM\RecallBundle\Tests\Card;

use MarilenaRM\RecallBundle\Card\Card;
use MarilenaRM\RecallBundle\Card\CardDraft;
use MarilenaRM\RecallBundle\Card\CardGenerator;
use MarilenaRM\RecallBundle\Card\Deck;
use MarilenaRM\RecallBundle\Card\DeckStorage;
use MarilenaRM\RecallBundle\Card\DeckSynchronizer;
use MarilenaRM\RecallBundle\Card\DuplicateFinder;
use MarilenaRM\RecallBundle\Card\SyncReport;
use MarilenaRM\RecallBundle\Fact\Fact;
use MarilenaRM\RecallBundle\Fact\FactExtractorInterface;
use MarilenaRM\RecallBundle\Fact\FactKind;
use MarilenaRM\RecallBundle\Tests\MockPlatform;
use PHPUnit\Framework\TestCase;

final class DeckSynchronizerTest extends TestCase
{
    private DeckStorage $storage;

    /** @var list<string> */
    private array $promptedFacts = [];

    /** @var list<string> */
    private array $failingFacts = [];

    /** @var list<string> questions the mocked model reports as duplicates */
    private array $duplicateQuestions = [];

    private int $deduplicationCalls = 0;

    private bool $deduplicationFails = false;

    protected function setUp(): void
    {
        $this->storage = new DeckStorage(sys_get_temp_dir().'/recall-deck-'.bin2hex(random_bytes(4)).'/deck.json');
    }

    public function testItGeneratesCardsForNewFacts(): void
    {
        $this->sync([self::fact('route:home', 'v1')]);

        self::assertSame(['route:home'], $this->promptedFacts);
        self::assertSame(['About route:home v1?'], $this->questions());
    }

    public function testItLeavesUnchangedFactsAlone(): void
    {
        $this->sync([self::fact('route:home', 'v1')]);
        $this->promptedFacts = [];

        $report = $this->sync([self::fact('route:home', 'v1')]);

        self::assertSame([], $this->promptedFacts);
        self::assertCount(1, $report->unchanged);
        self::assertSame(['About route:home v1?'], $this->questions());
    }

    public function testItRegeneratesTheCardsOfAFactWhoseSourceChanged(): void
    {
        $this->sync([self::fact('route:home', 'v1')]);

        $report = $this->sync([self::fact('route:home', 'v2')]);

        self::assertSame(['About route:home v1?'], array_map(static fn (Card $card): string => $card->question, $report->obsolete));
        self::assertSame(['About route:home v2?'], $this->questions());
    }

    public function testItRegeneratesTheCardsWrittenByAnEarlierPrompt(): void
    {
        $fact = self::fact('route:home', 'v1');
        // Written before prompt versions existed: the bare fact fingerprint.
        $this->storage->save(new Deck([$fact->id => $fact->fingerprint], [Card::fromDraft($fact, new CardDraft('Old question?', 'Old answer.'))]));

        $report = $this->sync([$fact]);

        self::assertSame(['route:home'], $this->promptedFacts);
        self::assertSame(['Old question?'], array_map(static fn (Card $card): string => $card->question, $report->obsolete));
        self::assertSame(['About route:home v1?'], $this->questions());
    }

    public function testItDropsTheCardsOfVanishedFacts(): void
    {
        $this->sync([self::fact('route:home', 'v1'), self::fact('route:legacy', 'v1')]);

        $report = $this->sync([self::fact('route:home', 'v1')]);

        self::assertSame(['route:legacy'], $report->vanished);
        self::assertSame(['About route:home v1?'], $this->questions());
    }

    public function testAFactThatYieldedNoCardIsNotSentToTheModelAgain(): void
    {
        $this->sync([self::fact('route:boilerplate', 'empty')]);
        $this->promptedFacts = [];

        $this->sync([self::fact('route:boilerplate', 'empty')]);

        self::assertSame([], $this->promptedFacts);
    }

    public function testAFactTheModelFailsOnKeepsItsCardsWithoutHoldingBackTheOthers(): void
    {
        $this->sync([self::fact('route:about', 'v1'), self::fact('route:home', 'v1')]);
        $this->failingFacts = ['route:about'];

        $synchronizer = $this->synchronizer([self::fact('route:about', 'v2'), self::fact('route:home', 'v2')]);
        $result = $synchronizer->apply($synchronizer->plan());

        self::assertSame(['route:about'], array_keys($result->failures));
        self::assertSame(['About route:about v1?', 'About route:home v2?'], $this->questions());
        self::assertCount(1, $synchronizer->plan()->toGenerate);
    }

    public function testAGeneratedCardDuplicatingAnotherIsDroppedAndNotGeneratedAgain(): void
    {
        $this->duplicateQuestions = ['About route:edit v1?'];

        $synchronizer = $this->synchronizer([self::fact('route:show', 'v1'), self::fact('route:edit', 'v1')]);
        $result = $synchronizer->apply($synchronizer->plan());

        self::assertSame(['About route:edit v1?'], array_map(static fn (Card $card): string => $card->question, $result->duplicates));
        self::assertSame(['About route:show v1?'], $this->questions());
        self::assertSame([], $synchronizer->plan()->toGenerate);
    }

    public function testCardsAlreadyInTheDeckAreNeverDroppedAsDuplicates(): void
    {
        $this->sync([self::fact('route:show', 'v1')]);
        $this->duplicateQuestions = ['About route:show v1?'];

        $this->sync([self::fact('route:show', 'v1'), self::fact('route:edit', 'v1')]);

        self::assertSame(['About route:edit v1?', 'About route:show v1?'], $this->questions());
    }

    public function testAFailedDeduplicationKeepsTheNewCards(): void
    {
        $this->deduplicationFails = true;

        $synchronizer = $this->synchronizer([self::fact('route:show', 'v1')]);
        $result = $synchronizer->apply($synchronizer->plan());

        self::assertNotNull($result->deduplicationFailure);
        self::assertSame(['About route:show v1?'], $this->questions());
    }

    public function testNothingIsDeduplicatedWhenNoCardWasGenerated(): void
    {
        $this->sync([self::fact('route:show', 'v1')]);
        $this->deduplicationCalls = 0;

        $this->sync([self::fact('route:show', 'v1')]);

        self::assertSame(0, $this->deduplicationCalls);
    }

    public function testCardsFollowTheirFactWhenItsCodeMovesWithinTheFile(): void
    {
        $this->sync([self::fact('route:home', 'v1', 'src/Controller.php:10')]);
        $this->promptedFacts = [];

        $this->sync([self::fact('route:home', 'v1', 'src/Controller.php:24')]);

        self::assertSame([], $this->promptedFacts);
        self::assertSame('src/Controller.php:24', $this->storage->load()->cards()[0]->source);
    }

    /**
     * @param list<Fact> $facts
     */
    private function sync(array $facts): SyncReport
    {
        $synchronizer = $this->synchronizer($facts);
        $report = $synchronizer->plan();
        self::assertTrue($synchronizer->apply($report)->isSuccessful());

        return $report;
    }

    /**
     * @param list<Fact> $facts
     */
    private function synchronizer(array $facts): DeckSynchronizer
    {
        $platform = MockPlatform::create(function (string $payload): string {
            if (str_contains($payload, 'NEW cards:')) {
                return $this->answerDeduplication($payload);
            }

            if (1 !== preg_match('/^Fact (\S+) (\S+)$/m', $payload, $matches)) {
                throw new \LogicException('The prompt should start with the fact title.');
            }
            [, $factId, $version] = $matches;
            $this->promptedFacts[] = $factId;

            if (\in_array($factId, $this->failingFacts, true)) {
                return 'not json';
            }

            return MockPlatform::answering('empty' === $version ? [] : [[\sprintf('About %s %s?', $factId, $version), 'Answer.']]);
        });

        $extractor = new class($facts) implements FactExtractorInterface {
            /** @param list<Fact> $facts */
            public function __construct(private readonly array $facts)
            {
            }

            public function extract(): iterable
            {
                return $this->facts;
            }
        };

        return new DeckSynchronizer(
            [$extractor],
            $this->storage,
            new CardGenerator($platform, 'mock-model', 2, 'English'),
            new DuplicateFinder($platform, 'mock-model'),
        );
    }

    /**
     * @return list<string>
     */
    private function questions(): array
    {
        $questions = array_map(static fn (Card $card): string => $card->question, $this->storage->load()->cards());
        sort($questions);

        return $questions;
    }

    private static function fact(string $id, string $version, string $source = 'src/Controller.php:1'): Fact
    {
        return new Fact($id, FactKind::Route, 'Fact '.$id.' '.$version, 'Body '.$version, $source);
    }

    private function answerDeduplication(string $payload): string
    {
        ++$this->deduplicationCalls;

        if ($this->deduplicationFails) {
            return 'not json';
        }

        preg_match_all('/^\[(\w+)\] Q: (.+?) A: /m', $payload, $matches, \PREG_SET_ORDER);
        $ids = [];
        foreach ($matches as [, $id, $question]) {
            if (\in_array($question, $this->duplicateQuestions, true)) {
                $ids[] = $id;
            }
        }

        return json_encode(['ids' => $ids], \JSON_THROW_ON_ERROR);
    }
}
