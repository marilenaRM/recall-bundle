# Card prompt comparison

Generates cards from the same facts with the card prompt before and after 613d8c7 ("Harden the card
prompt against compound questions and document labels"), then counts compound questions and document
labels in each version.

```bash
ANTHROPIC_API_KEY=... bench/card-prompt-comparison/run.sh
```

What it does:

1. Checks out both revisions (`613d8c7^` and `613d8c7`) in temporary git worktrees, runs `composer
   install` in each and adds `symfony/ai-anthropic-platform` there. Your checkout and its
   `composer.json` are not touched.
2. Extracts the facts once, from the "after" checkout, with the bundle's own extractors: the fixture
   ADRs, entities and checkout route from `tests/Fixtures`, plus the two ADRs in `adr/`, written to
   be full of option numbers, ticket and ADR references and "the document above".
3. Calls `CardGenerator::generate()` from each checkout `RUNS` times per fact, with the same model and
   settings: 6 facts × 2 versions × 3 runs = 36 API calls by default.
4. Writes `out/report.md`: a before/after table, then every card side by side with its flags.

Settings, all optional: `RUNS` (3), `MODEL` (`claude-sonnet-5-5`), `LANGUAGE` (English), `MAX_CARDS`
(3), `BEFORE`/`AFTER`, `OUT`. `DRY_RUN=1` stops after `out/facts.json`, with no API call.
`KEEP_WORKTREES=1` keeps the worktrees and their `vendor/`; pass the printed `WORKDIR=...` to a later
run to skip the installs.

The flags are heuristics that point at cards worth reading: a compound flag fires on any "and" or
"or", for instance. Confirm each flagged card before quoting the numbers. `php report.php out`
rebuilds the report from `before.json` and `after.json` without calling the API again.
