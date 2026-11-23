<?php

declare(strict_types=1);

/*
 * Generates cards for every fact with the CardGenerator of the given checkout, RUNS times each.
 *
 *   php generate.php <checkout> <facts.json> <out.json> <label> <model> <runs> <max-cards> <language>
 *
 * Reads the key from ANTHROPIC_API_KEY. A failed call is recorded and the run goes on, except an
 * authentication failure: every other call would fail the same way, so the run stops there.
 */

use MarilenaRM\RecallBundle\Card\CardGenerator;
use MarilenaRM\RecallBundle\Fact\Fact;
use MarilenaRM\RecallBundle\Fact\FactKind;
use Symfony\AI\Platform\Bridge\Anthropic\Factory;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcher;

if (\count($argv) < 9) {
    fwrite(\STDERR, "Usage: php generate.php <checkout> <facts.json> <out.json> <label> <model> <runs> <max-cards> <language>\n");
    exit(1);
}
[, $checkout, $factsFile, $output, $label, $model, $runs, $maxCards, $language] = $argv;
$runs = (int) $runs;
$maxCards = (int) $maxCards;

$apiKey = getenv('ANTHROPIC_API_KEY');
if (false === $apiKey || '' === $apiKey) {
    fwrite(\STDERR, "ANTHROPIC_API_KEY is not set.\n");
    exit(1);
}

// The checkout's autoloader, so that its own CardGenerator (and prompt) is the one used.
require $checkout.'/vendor/autoload.php';

$dispatcher = new EventDispatcher();
$dispatcher->addSubscriber(new PlatformSubscriber());
$platform = Factory::createPlatform($apiKey, eventDispatcher: $dispatcher);
$generator = new CardGenerator($platform, $model, $maxCards, $language);

$facts = json_decode((string) file_get_contents($factsFile), true, flags: \JSON_THROW_ON_ERROR);
$promptVersion = \defined(CardGenerator::class.'::PROMPT_VERSION') ? constant(CardGenerator::class.'::PROMPT_VERSION') : 1;

$results = [];
$total = \count($facts) * $runs;
$done = 0;
foreach ($facts as $data) {
    $fact = new Fact($data['id'], FactKind::from($data['kind']), $data['title'], $data['body'], $data['source']);

    for ($run = 1; $run <= $runs; ++$run) {
        ++$done;
        fprintf(\STDERR, "[%s %d/%d] %s, run %d... ", $label, $done, $total, $fact->id, $run);
        $start = microtime(true);
        $entry = ['fact_id' => $fact->id, 'run' => $run, 'cards' => [], 'error' => null];

        try {
            foreach ($generator->generate($fact) as $card) {
                $entry['cards'][] = ['question' => $card->question, 'answer' => $card->answer];
            }
            fprintf(\STDERR, "%d cards\n", \count($entry['cards']));
        } catch (AuthenticationException $e) {
            fprintf(\STDERR, "ERROR %s\nAuthentication failed: check ANTHROPIC_API_KEY. Stopping.\n", $e->getMessage());
            exit(1);
        } catch (Throwable $e) {
            $entry['error'] = $e::class.': '.$e->getMessage();
            fprintf(\STDERR, "ERROR %s\n", $entry['error']);
        }

        $entry['seconds'] = round(microtime(true) - $start, 1);
        $results[] = $entry;
    }
}

file_put_contents($output, json_encode([
    'label' => $label,
    'commit' => trim((string) shell_exec('git -C '.escapeshellarg($checkout).' log -1 --format=%h')),
    'prompt_version' => $promptVersion,
    'model' => $model,
    'runs' => $runs,
    'max_cards' => $maxCards,
    'language' => $language,
    'results' => $results,
], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n");
