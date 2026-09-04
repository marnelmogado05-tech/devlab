<?php

namespace App\Services\Challenge\CodeArena;

use App\Models\Challenge;
use Illuminate\Support\Facades\Validator;

/**
 * Validates `challenges.configuration` and `solution` for Code Arena, generates
 * the sandbox harness, and owns the one definition of "this case passed".
 *
 * The contract is in docs/experiences/code-arena.md.
 *
 * Three things justify this class rather than a rules array in a request:
 *
 *  1. The harness is GENERATED from the configuration, from the case inputs and
 *     nothing else. Expected outputs are never assembled into anything that
 *     crosses into the sandbox (ADR 0008), and that property is only checkable
 *     if one object builds the payload.
 *  2. The consistency checks catch challenges that are well-formed and
 *     worthless — a key shorter than the case list, a sample whose shown answer
 *     contradicts the real one, an expectation every constant satisfies.
 *  3. Grading is read by two callers — the evaluator, which decides, and the
 *     runs endpoint, which reports progress. Two implementations of "matches"
 *     would eventually disagree, and the one the player saw would not be the one
 *     that scored them.
 */
class CodeArenaConfiguration
{
    /** Sandbox images the orchestrator will run. Refused by name if not here. */
    public const RUNTIMES = ['php-8.4'];

    /** Below this, one hidden case is the whole test and luck passes it. */
    private const MINIMUM_CASES = 3;

    /**
     * @return array<int, string> the problems found; empty means valid
     */
    public function problems(Challenge $challenge): array
    {
        $validator = Validator::make(
            ['configuration' => $challenge->configuration, 'solution' => $challenge->solution],
            [
                'configuration.runtime' => ['required', 'string', 'in:'.implode(',', self::RUNTIMES)],
                'configuration.entry' => ['required', 'string', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/', 'max:60'],
                'configuration.signature' => ['required', 'string', 'max:200'],
                'configuration.brief' => ['required', 'string', 'max:2000'],
                'configuration.starter' => ['required', 'string', 'max:8000'],
                'configuration.cases' => ['required', 'array', 'min:'.self::MINIMUM_CASES, 'max:'.$this->maxCases()],
                'configuration.cases.*.args' => ['present', 'array'],
                'configuration.cases.*.sample' => ['required', 'boolean'],
                'configuration.cases.*.label' => ['nullable', 'string', 'max:120'],
                'solution.expected' => ['required', 'array', 'min:'.self::MINIMUM_CASES],
                'solution.reference' => ['required', 'string', 'max:8000'],
            ],
        );

        $problems = array_merge(...array_values($validator->errors()->toArray())) ?: [];

        if ($problems !== []) {
            return $problems;
        }

        return $this->consistencyProblems($challenge);
    }

    public function isValid(Challenge $challenge): bool
    {
        return $this->problems($challenge) === [];
    }

    public function runtime(Challenge $challenge): string
    {
        return (string) ($challenge->configuration['runtime'] ?? 'php-8.4');
    }

    /**
     * The cases, in order.
     *
     * The index is the case's identity everywhere: in the harness payload, in
     * what the sandbox prints, in `observed`, and in the answer key. Nothing
     * reorders them.
     *
     * @return array<int, array<string, mixed>>
     */
    public function cases(Challenge $challenge): array
    {
        /** @var array<int, array<string, mixed>> $cases */
        $cases = array_values($challenge->configuration['cases'] ?? []);

        return $cases;
    }

    /**
     * The answer key, positional. Read from `solution`, which the controller
     * never sends to a client (§72).
     *
     * @return array<int, mixed>
     */
    public function expected(Challenge $challenge): array
    {
        return array_values($challenge->solution['expected'] ?? []);
    }

    /**
     * The test bundle the sandbox runs.
     *
     * Built from case ARGUMENTS only. Nothing derived from `solution` is
     * reachable from here, which is the property ADR 0008 turns on: code inside
     * the sandbox cannot fake a pass because nothing in there knows what one is.
     *
     * @param  int  $budgetSeconds  the sandbox's own timeout; the harness must
     *                              finish inside it, or the orchestrator kills
     *                              the container and every case is lost at once
     */
    public function harness(Challenge $challenge, int $budgetSeconds): string
    {
        $arguments = array_map(
            static fn (array $case): array => array_values($case['args'] ?? []),
            $this->cases($challenge),
        );

        return strtr(self::HARNESS, [
            '{{ENTRY}}' => base64_encode((string) $challenge->configuration['entry']),
            '{{CASES}}' => base64_encode((string) json_encode($arguments)),
            '{{CHILD}}' => base64_encode(self::CHILD),
            '{{BUDGET}}' => (string) max(1, $budgetSeconds),
        ]);
    }

    /**
     * Compare what the sandbox observed against the key.
     *
     * Returns one entry per case IN THE CHALLENGE, not one per line the sandbox
     * printed. A run that produced nothing for case 4 fails case 4; it does not
     * shorten the test.
     *
     * @param  array<int, mixed>|null  $observed
     * @return array{cases: array<int, array<string, mixed>>, passed: int, total: int}
     */
    public function grade(Challenge $challenge, ?array $observed): array
    {
        $expected = $this->expected($challenge);
        $byCase = [];

        foreach ($observed ?? [] as $entry) {
            if (is_array($entry) && isset($entry['case']) && is_int($entry['case'])) {
                /*
                 * First writer wins. The harness emits one line per case; a
                 * second line for a case already reported is either a bug or an
                 * attempt to overwrite an answer, and neither should replace it.
                 */
                $byCase[$entry['case']] ??= $entry;
            }
        }

        $results = [];
        $passed = 0;

        foreach ($this->cases($challenge) as $index => $case) {
            $entry = $byCase[$index] ?? null;
            $sample = ($case['sample'] ?? false) === true;

            $status = $entry === null ? 'missing' : (string) ($entry['status'] ?? 'error');

            $ok = $status === 'ok'
                && ($entry['has_value'] ?? false) === true
                && $this->matches($expected[$index] ?? null, $entry['value'] ?? null);

            $passed += $ok ? 1 : 0;

            $results[] = [
                'case' => $index,
                'label' => $case['label'] ?? null,
                'sample' => $sample,
                'status' => $status,
                'passed' => $ok,
                /*
                 * Inputs are public for every case; only the ANSWER is withheld.
                 * Showing a hidden case's input is what makes a failure
                 * diagnosable without making it guessable — a player can see
                 * what they were asked and still has to work out the answer.
                 */
                'args' => array_values($case['args'] ?? []),
                'expected' => $sample ? ($expected[$index] ?? null) : null,
                /*
                 * Their own return value, on every case. It is their code's
                 * output, not the key: handing it back cannot tell them anything
                 * they could not have printed for themselves.
                 */
                'returned' => $entry === null ? null : ($entry['value'] ?? null),
                'has_value' => ($entry['has_value'] ?? false) === true,
                'output' => $entry === null ? null : ($entry['output'] ?? null),
                'duration_ms' => $entry === null ? null : ($entry['ms'] ?? null),
            ];
        }

        return ['cases' => $results, 'passed' => $passed, 'total' => count($results)];
    }

    /**
     * Whether a returned value is the expected one.
     *
     * Arrays are compared by content, not by key order: `===` on arrays is
     * order-sensitive, so a correct answer assembled by a different route would
     * fail for a reason with nothing to do with the problem. Everything else is
     * compared strictly, because a challenge whose answer is `0` must not be
     * satisfied by `false`.
     *
     * There is deliberately no float tolerance. The validator refuses float
     * expectations outright rather than inventing an epsilon nobody chose — see
     * `consistencyProblems`.
     */
    public function matches(mixed $expected, mixed $actual): bool
    {
        if (is_array($expected) && is_array($actual)) {
            return $this->canonical($expected) === $this->canonical($actual);
        }

        return $expected === $actual;
    }

    /**
     * @return array<int, string>
     */
    private function consistencyProblems(Challenge $challenge): array
    {
        $problems = [];

        $cases = $this->cases($challenge);
        $expected = $this->expected($challenge);
        $configuration = $challenge->configuration;

        /*
         * The check this class exists for. A key with fewer entries than the
         * case list does not fail loudly: the missing cases grade against null,
         * every submission fails them, and the challenge looks unreasonably hard
         * rather than broken.
         */
        if (count($expected) !== count($cases)) {
            $problems[] = 'The answer key has '.count($expected).' entries for '
                .count($cases).' cases; it must have exactly one per case.';
        }

        $arity = null;

        foreach ($cases as $index => $case) {
            $args = array_values($case['args'] ?? []);
            $arity ??= count($args);

            if (count($args) !== $arity) {
                $problems[] = "Case {$index} takes ".count($args).' arguments; case 0 takes '
                    .$arity.'. Every case must call the same signature.';
            }

            /*
             * A sample shows its answer to the player. If that answer is not the
             * one being graded, the challenge lies in its own worked example.
             */
            if (($case['sample'] ?? false) === true) {
                if (! array_key_exists('expected', $case)) {
                    $problems[] = "Sample case {$index} must show its expected value.";
                } elseif (array_key_exists($index, $expected)
                    && ! $this->matches($expected[$index], $case['expected'])) {
                    $problems[] = "Sample case {$index} shows an expected value that differs "
                        .'from the answer key.';
                }
            } elseif (array_key_exists('expected', $case)) {
                $problems[] = "Case {$index} is hidden but carries its expected value in "
                    .'`configuration`, which is sent to the client. Move it to `solution`.';
            }
        }

        if (! $this->hasHiddenCase($cases)) {
            $problems[] = 'At least one case must be hidden. A challenge whose whole key is '
                .'visible tests transcription, not implementation.';
        }

        /*
         * If every case expects the same thing, `return true;` scores full marks
         * without reading the brief. This does not prove a challenge is
         * solvable; it proves it is not trivially satisfiable, which is the
         * failure that actually reaches production.
         */
        if (count($expected) > 1
            && count(array_unique(array_map($this->canonicalJson(...), $expected))) === 1) {
            $problems[] = 'Every case expects the same value, so a function that ignores its '
                .'input passes them all.';
        }

        foreach ($expected as $index => $value) {
            if ($this->containsFloat($value)) {
                $problems[] = "Expected value {$index} contains a float. Grading is exact "
                    .'equality, and exact equality on floats is not a promise the language '
                    .'makes — return an integer, or a rounded decimal as a string.';
            }
        }

        $entry = (string) ($configuration['entry'] ?? '');

        /*
         * The starter is what the player begins from. If it does not define the
         * function being graded, every first run fails for a reason the player
         * did not cause.
         */
        if ($entry !== '' && ! str_contains((string) ($configuration['starter'] ?? ''), $entry)) {
            $problems[] = "The starter code does not mention `{$entry}`, which is the function "
                .'every case calls.';
        }

        if ($entry !== '' && ! str_contains((string) ($challenge->solution['reference'] ?? ''), $entry)) {
            $problems[] = "The reference solution does not define `{$entry}`.";
        }

        return $problems;
    }

    /**
     * @param  array<int, array<string, mixed>>  $cases
     */
    private function hasHiddenCase(array $cases): bool
    {
        foreach ($cases as $case) {
            if (($case['sample'] ?? false) !== true) {
                return true;
            }
        }

        return false;
    }

    private function containsFloat(mixed $value): bool
    {
        if (is_float($value)) {
            return true;
        }

        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if ($this->containsFloat($item)) {
                return true;
            }
        }

        return false;
    }

    private function canonicalJson(mixed $value): string
    {
        return (string) json_encode(is_array($value) ? $this->canonical($value) : $value);
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function canonical(array $value): array
    {
        $sorted = [];

        foreach ($value as $key => $item) {
            $sorted[$key] = is_array($item) ? $this->canonical($item) : $item;
        }

        // Lists keep their order — it is meaningful. Maps do not have one, so
        // sorting keys makes two equal maps compare equal.
        if (! array_is_list($sorted)) {
            ksort($sorted);
        }

        return $sorted;
    }

    private function maxCases(): int
    {
        return (int) config('devlab.execution.max_cases', 12);
    }

    /**
     * The parent harness, which never loads the submission.
     *
     * It writes the child script, then runs one child per case: unlink the
     * result file, run, read it back, print a line describing what happened.
     * Because it never `require`s anything the player wrote, nothing the player
     * wrote can reach its bookkeeping — the worst a submission can do is lie
     * about its own return value, which is indistinguishable from returning the
     * wrong answer, and returning the wrong answer is allowed.
     */
    private const HARNESS = <<<'HARNESS'
<?php

/* Generated by DevLab. Runs inside the sandbox, as the parent process. */

$entry = base64_decode('{{ENTRY}}');
$cases = json_decode(base64_decode('{{CASES}}'), true) ?: [];
$deadline = microtime(true) + {{BUDGET}} - 0.5;

file_put_contents('/tmp/case.php', base64_decode('{{CHILD}}'));

/* Per case, so one program printing forever cannot fill this process. */
$outputCap = 2000;
$slice = count($cases) > 0 ? max(0.25, ({{BUDGET}} - 0.75) / count($cases)) : 0.25;

foreach ($cases as $index => $args) {
    $remaining = $deadline - microtime(true);

    if ($remaining <= 0.05) {
        echo json_encode(['case' => $index, 'status' => 'timeout', 'has_value' => false]), "\n";

        continue;
    }

    @unlink('/tmp/value.json');

    $payload = base64_encode(json_encode(['entry' => $entry, 'args' => $args]));
    $started = microtime(true);

    $process = @proc_open(
        [PHP_BINARY, '/tmp/case.php', $payload],
        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );

    if (! is_resource($process)) {
        echo json_encode(['case' => $index, 'status' => 'error', 'has_value' => false]), "\n";

        continue;
    }

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $printed = '';
    $timedOut = false;
    $exitCode = null;
    $end = microtime(true) + min($slice, $remaining);

    while (true) {
        $chunk = (string) stream_get_contents($pipes[1]).(string) stream_get_contents($pipes[2]);

        if ($chunk !== '' && strlen($printed) < $outputCap) {
            $printed .= substr($chunk, 0, $outputCap - strlen($printed));
        }

        $status = proc_get_status($process);

        if (! $status['running']) {
            $exitCode = $status['exitcode'];

            break;
        }

        if (microtime(true) >= $end) {
            $timedOut = true;
            proc_terminate($process, 9);

            break;
        }

        usleep(2000);
    }

    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    $raw = @file_get_contents('/tmp/value.json');
    $result = $raw === false ? null : json_decode($raw, true);
    @unlink('/tmp/value.json');

    $hasValue = ! $timedOut && is_array($result) && array_key_exists('value', $result);

    echo json_encode([
        'case' => $index,
        'status' => $timedOut ? 'timeout' : ($hasValue ? 'ok' : 'error'),
        'has_value' => $hasValue,
        'value' => $hasValue ? $result['value'] : null,
        'output' => $printed,
        'exit_code' => $exitCode,
        'ms' => (int) round((microtime(true) - $started) * 1000),
    ]), "\n";
}
HARNESS;

    /**
     * The child, which loads the submission and does exactly one thing with it.
     *
     * It writes the returned value to a file rather than printing it, so a
     * submission that prints — deliberately, or by leaving a `var_dump` behind —
     * cannot be mistaken for one that returned.
     */
    private const CHILD = <<<'CHILD'
<?php

/* Generated by DevLab. One case, in its own process. */

$payload = json_decode(base64_decode((string) ($argv[1] ?? '')), true);

if (! is_array($payload)) {
    exit(3);
}

require '/tmp/submission.php';

/*
 * After the require, before the call — so a value written by the submission's
 * TOP LEVEL cannot be read as something the function returned.
 *
 * This does not stop a submission writing the file from inside the function, or
 * writing it and exiting before this line is ever reached. Nothing can: code
 * sharing this process can write anything this process can write, which is why
 * ADR 0008 puts the verdict on the other side of the boundary instead of trying
 * to make this file trustworthy.
 *
 * What that buys is measured rather than assumed. A submission that forges
 * result lines, pre-writes a guess and exits scores on exactly the cases its
 * guess happens to fit — two of four against a key containing that value, and
 * ZERO of four against a key that does not. Claiming a value and returning one
 * are the same act, and both are worthless without knowing the answer.
 */
@unlink('/tmp/value.json');

$entry = (string) $payload['entry'];

if (! function_exists($entry)) {
    fwrite(STDERR, "No function named {$entry} was defined.\n");

    exit(4);
}

$value = $entry(...array_values($payload['args']));

file_put_contents(
    '/tmp/value.json',
    json_encode(['value' => $value], JSON_PARTIAL_OUTPUT_ON_ERROR),
);
CHILD;
}
