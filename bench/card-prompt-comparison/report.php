<?php

declare(strict_types=1);

/*
 * Compares before.json and after.json and writes report.md next to them. Needs no dependency.
 *
 *   php report.php <out-dir>
 *
 * The flags are heuristics that point at cards to read, not a verdict: count what you confirm.
 */

$dir = $argv[1] ?? null;
if (null === $dir) {
    fwrite(\STDERR, "Usage: php report.php <out-dir>\n");
    exit(1);
}

/**
 * A question that probably asks two things.
 *
 * An "and" joining two verbs ("record the order and dispatch a message") and a comma closing a
 * leading clause ("When Redis is down, why...") ask one thing: only a question word after the
 * "and"/"or", or after a comma that does not close a leading clause, starts a second ask.
 */
function compoundReasons(string $question): array
{
    $wh = '(what|why|how|when|which|who|where)';
    $reasons = [];
    if (substr_count($question, '?') > 1) {
        $reasons[] = 'several "?"';
    }
    if (preg_match('/\b(and|or)\s+'.$wh.'\b/i', $question, $m)) {
        $reasons[] = '"'.strtolower($m[1].' '.$m[2]).'"';
    }
    if (!preg_match('/^(if|when|whenever|once|since|because|unless|while|after|before|although|even though|as soon as)\b/i', $question)
        && preg_match('/,\s*'.$wh.'\b/i', $question)) {
        $reasons[] = 'comma before a second ask';
    }

    return $reasons;
}

/** Internal labels of the source material. */
function labelMatches(string $text): array
{
    $patterns = [
        '/\b(option|proposition|alternative|choice|variant)\s+(\d+|[A-Z])\b/i',
        '/\bADR[-\s#]*\d+/i',
        '/\b(ticket|incident|issue|bug)\s*#?\s*[A-Z]*-?\d+/i',
        '/\b[A-Z]{2,}-\d+\b/',
        '/(?<![\w&])#\d+\b/',
        '/\bsection\s+\d+/i',
        '/\bthe\s+(document|decision above|above decision|decision record|adr|material|text|source)\b/i',
    ];
    $found = [];
    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $text, $m)) {
            array_push($found, ...$m[0]);
        }
    }

    return array_values(array_unique($found));
}

$versions = [];
foreach (['before', 'after'] as $label) {
    $file = $dir.'/'.$label.'.json';
    if (!is_file($file)) {
        fwrite(\STDERR, "Missing $file\n");
        exit(1);
    }
    $versions[$label] = json_decode((string) file_get_contents($file), true, flags: \JSON_THROW_ON_ERROR);
}

$stats = [];
$annotated = [];
foreach ($versions as $label => $data) {
    $s = ['calls' => 0, 'errors' => 0, 'empty' => 0, 'cards' => 0, 'compound' => 0, 'label_q' => 0, 'label_a' => 0, 'q_words' => 0];
    foreach ($data['results'] as $result) {
        ++$s['calls'];
        if (null !== $result['error']) {
            ++$s['errors'];
            $annotated[$label][$result['fact_id']][$result['run']] = 'ERROR: '.$result['error'];
            continue;
        }
        if ([] === $result['cards']) {
            ++$s['empty'];
        }
        foreach ($result['cards'] as $card) {
            ++$s['cards'];
            $s['q_words'] += str_word_count($card['question']);
            $compound = compoundReasons($card['question']);
            $labelsQ = labelMatches($card['question']);
            $labelsA = labelMatches($card['answer']);
            $s['compound'] += [] !== $compound ? 1 : 0;
            $s['label_q'] += [] !== $labelsQ ? 1 : 0;
            $s['label_a'] += [] !== $labelsA ? 1 : 0;
            $annotated[$label][$result['fact_id']][$result['run']][] = $card + ['compound' => $compound, 'labels_q' => $labelsQ, 'labels_a' => $labelsA];
        }
        $annotated[$label][$result['fact_id']][$result['run']] ??= [];
    }
    $stats[$label] = $s;
}

$pct = static fn (int $n, int $of): string => 0 === $of ? '–' : \sprintf('%d (%d%%)', $n, round(100 * $n / $of));
$row = static function (string $name, callable $value) use ($stats): string {
    return \sprintf("| %s | %s | %s |\n", $name, $value($stats['before']), $value($stats['after']));
};

$b = $versions['before'];
$a = $versions['after'];
$md = "# Card prompt: before vs after 613d8c7\n\n";
$md .= \sprintf("- Before: `%s` (prompt version %s)\n- After: `%s` (prompt version %s)\n", $b['commit'], $b['prompt_version'], $a['commit'], $a['prompt_version']);
$md .= \sprintf("- Model `%s`, %d runs per fact, at most %d cards per fact, cards in %s\n\n", $a['model'], $a['runs'], $a['max_cards'], $a['language']);
$md .= "The flags are heuristics: they point at cards worth reading. Confirm each one by hand before quoting the numbers.\n\n";
$md .= "| | Before | After |\n|---|---|---|\n";
$md .= $row('Calls (errors)', static fn ($s) => \sprintf('%d (%d)', $s['calls'], $s['errors']));
$md .= $row('Cards', static fn ($s) => (string) $s['cards']);
$md .= $row('Cards per successful call', static fn ($s) => $s['calls'] - $s['errors'] > 0 ? number_format($s['cards'] / ($s['calls'] - $s['errors']), 2) : '–');
$md .= $row('Calls returning no card', static fn ($s) => (string) $s['empty']);
$md .= $row('Questions flagged compound', static fn ($s) => $pct($s['compound'], $s['cards']));
$md .= $row('Questions with a document label', static fn ($s) => $pct($s['label_q'], $s['cards']));
$md .= $row('Answers with a document label', static fn ($s) => $pct($s['label_a'], $s['cards']));
$md .= $row('Words per question', static fn ($s) => $s['cards'] > 0 ? number_format($s['q_words'] / $s['cards'], 1) : '–');

$factIds = array_values(array_unique(array_merge(array_keys($annotated['before'] ?? []), array_keys($annotated['after'] ?? []))));
$md .= "\n## Cards by fact\n\nFlags: **[C: reason]** compound question, **[L: label]** label in the question, **[LA: label]** label in the answer.\n";
foreach ($factIds as $factId) {
    $md .= "\n### `$factId`\n";
    foreach (['before', 'after'] as $label) {
        $md .= "\n#### ".ucfirst($label)."\n";
        foreach ($annotated[$label][$factId] ?? [] as $run => $cards) {
            $md .= "\nRun $run:\n";
            if (\is_string($cards)) {
                $md .= "- $cards\n";
                continue;
            }
            if ([] === $cards) {
                $md .= "- _(no card)_\n";
                continue;
            }
            foreach ($cards as $card) {
                $flags = '';
                if ([] !== $card['compound']) {
                    $flags .= ' **[C: '.implode(', ', $card['compound']).']**';
                }
                if ([] !== $card['labels_q']) {
                    $flags .= ' **[L: '.implode(', ', $card['labels_q']).']**';
                }
                if ([] !== $card['labels_a']) {
                    $flags .= ' **[LA: '.implode(', ', $card['labels_a']).']**';
                }
                $md .= \sprintf("- **Q:** %s%s\n  **A:** %s\n", $card['question'], $flags, $card['answer']);
            }
        }
    }
}

file_put_contents($dir.'/report.md', $md);
echo strstr($md, "\n## Cards by fact", true), "\n";
