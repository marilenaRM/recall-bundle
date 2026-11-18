# RecallBundle

Spaced-repetition onboarding for Symfony projects.

The bundle reads what a project knows about itself — routes and their controllers, Doctrine
entities, architecture decision records — asks a [Symfony AI](https://symfony.com/doc/current/ai/index.html)
platform to turn each of them into a few review cards, and schedules the reviews per developer with
[FSRS](https://github.com/open-spaced-repetition/fsrs4anki/wiki/The-Algorithm), so that what a
developer is about to forget comes back just before they forget it.

> **Status: spike.** It exists to measure whether generated cards are worth reviewing. The API will
> change.

## What makes it different from a flashcard app

- **Cards are grounded.** Every card points at the file and line, or the document, it was written
  from. The model is told to ask nothing the material does not say.
- **Cards expire with the code.** Each fact is fingerprinted. When a controller, an entity or an ADR
  changes, its cards are regenerated on the next sync; when it disappears, so do its cards. A new
  release that improves the card prompt regenerates the whole deck once, on the first sync after the
  upgrade: progress is kept on cards that come back with the same question, and lost on the others.
- **One rule, one card.** Cards are written one fact at a time, so the same rule tends to come back
  on several facts (the second argument of `#[IsGranted]` on every protected route). After each sync,
  the model compares the new cards with the deck and drops the ones testing knowledge already
  covered. Cards already in the deck are never dropped, so nobody loses review progress. Two known
  limits: an interrupted sync leaves its new cards undeduplicated, and when the fact behind a kept
  card changes, the dropped duplicate does not come back.
- **The deck is shared, progress is personal.** `recall/deck.json` is committed and reviewed like any
  other file. Each developer's progress stays in `var/recall/`.

## Installation

Symfony AI 1.0 is still in beta: an application on `minimum-stability: stable` has to allow it
explicitly.

```bash
composer require --dev marilenarm/recall-bundle symfony/ai-platform:^1.0@beta symfony/ai-bundle:^1.0@beta symfony/ai-anthropic-platform:^1.0@beta
```

Enable the bundle in `dev` only — reviews happen on a developer machine, never in production:

```php
// config/bundles.php
MarilenaRM\RecallBundle\MarilenaRMRecallBundle::class => ['dev' => true],
```

If `symfony/ai-bundle` is installed only for this purpose, scope it to `dev` as well: its recipe
registers it for every environment, which breaks a `--no-dev` build.

```yaml
# config/packages/marilena_rm_recall.yaml
when@dev:
    marilena_rm_recall:
        model: claude-sonnet-5-5   # any model of the platform's catalog supporting structured output
        language: French           # language of the cards, English by default
        # max_output_tokens: 8192  # per call, thinking included; the Anthropic bridge defaults to 1000
        # platform: ai.platform.anthropic   # defaults to the PlatformInterface alias
        # decision_paths: ['%kernel.project_dir%/docs/adr']
```

`symfony/ai-bundle` 1.0.0-BETA1 crashes on structured output when the framework serializer is
disabled (`SerializerDescriber::__construct(): Argument #1 ($classMetadataFactory) must be of type
ClassMetadataFactoryInterface, null given`). Until it is fixed upstream, enable it:

```yaml
# config/packages/framework.yaml
when@dev:
    framework:
        serializer: true
```

## Usage

```bash
bin/console recall:sync --dry-run   # list the facts that would be sent to the model, no API call
bin/console recall:sync             # (re)generate the cards of new and changed facts
bin/console recall:review           # review today's cards
```

`recall:sync` is meant to run on the main branch (in CI, or by whoever merges), with the resulting
`recall/deck.json` committed. `recall:review --new=10` changes how many never-seen cards a session
introduces. A new card is shown with its answer rather than asked: there is nothing to recall yet.
Its first test comes the next day, and from then on each answer is rated from 1 (forgotten) to 4
(easy). New cards alternate between facts and kinds, so a session mixes decisions, entities and
routes.

## Adding a source of facts

Implement `FactExtractorInterface`; the service is picked up through autoconfiguration.

```php
final readonly class GlossaryFactExtractor implements FactExtractorInterface
{
    public function extract(): iterable
    {
        yield new Fact(
            id: 'glossary:credit-note',
            kind: FactKind::Decision,
            title: 'Credit note',
            body: 'A credit note cancels all or part of an issued invoice...',
            source: 'docs/glossary.md',
        );
    }
}
```

## Development

```bash
composer qa   # PHPStan at max level, then PHPUnit
```
