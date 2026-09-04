<?php

namespace App\Services\Challenge\CodeArena;

use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\ExecutionRun;
use App\Services\Challenge\AttemptScopedRules;
use App\Services\Challenge\ChallengeEvaluator;
use App\Services\Challenge\EvaluationResult;
use Illuminate\Validation\Rule;
use LogicException;

/**
 * Grades a Code Arena submission against a run that has already happened.
 *
 * The submission is a run id, not code. That is the whole point of ADR 0008: by
 * the time this runs, a sandbox has already produced a value for every case, and
 * this compares those values to a key the sandbox never received. So evaluation
 * is what every other experience's is — synchronous, deterministic, one row read
 * — and can sit inside `SubmitAttempt`'s transaction without holding a lock open
 * across somebody else's `while (true)`.
 *
 * Which runs are nameable is decided in validation, by `attemptSubmissionRules`.
 * A run belonging to another attempt, or one that never finished, is rejected
 * before this method is reached — so a platform failure cannot be submitted and
 * turned into a wrong answer (S7).
 */
class CodeArenaEvaluator implements AttemptScopedRules, ChallengeEvaluator
{
    public function __construct(private readonly CodeArenaConfiguration $configuration) {}

    public function evaluate(Challenge $challenge, array $submission): EvaluationResult
    {
        $runId = $submission['run_id'] ?? null;

        /*
         * Narrowed to an int before it reaches `find`. The payload is validated
         * upstream, but `find` given an ARRAY returns a Collection rather than a
         * model — so an evaluator that trusted the shape would, on any path that
         * skipped validation, call `isSubmittable()` on a collection and 500.
         */
        $run = is_int($runId) || (is_string($runId) && ctype_digit($runId))
            ? ExecutionRun::query()->find((int) $runId)
            : null;

        if ($run === null || ! $run->isSubmittable()) {
            /*
             * Reachable only if the run changed between validation and here.
             * Incorrect rather than an exception: a 500 on submit would close
             * nothing and lose the attempt's place, and the player did nothing
             * wrong.
             */
            return EvaluationResult::incorrect(
                feedback: 'That run is no longer available. Run your code again.',
                details: ['reason' => 'run_missing'],
            );
        }

        $graded = $this->configuration->grade($challenge, $run->observed);

        $total = $graded['total'];
        $passed = $graded['passed'];

        // A challenge with no cases cannot be passed, and must not be scored as
        // if it were. The validator refuses to publish one; this is the guard
        // for a row that got there another way.
        $accuracy = $total > 0 ? $passed / $total : 0.0;

        $details = [
            'run_id' => $run->id,
            'passed' => $passed,
            'total' => $total,
            /*
             * Per-case verdicts, kept server-side. `details` is not sent to the
             * client (§72) — which is what lets this hold the hidden cases'
             * expectations for dispute resolution without publishing them.
             */
            'cases' => $graded['cases'],
        ];

        if ($total > 0 && $passed === $total) {
            return EvaluationResult::correct(
                accuracy: 1.0,
                feedback: "All {$total} cases passed.",
                details: $details,
            );
        }

        return EvaluationResult::incorrect(
            /*
             * Partial credit, as System Design Lab established: code that
             * handles the ordinary cases and misses the empty one is a real
             * answer, and scoring it zero teaches nothing about which half was
             * wrong.
             */
            accuracy: $accuracy,
            feedback: $this->feedbackFor($graded),
            details: $details,
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws LogicException always
     */
    public function submissionRules(Challenge $challenge): array
    {
        /*
         * A run id means nothing without an attempt to scope it to, and the
         * rules that CAN be written here — required, integer, exists — would
         * accept any run belonging to anybody. Throwing follows
         * EvaluatorRegistry's precedent: a caller that reaches this has a wiring
         * mistake, and a loud failure is better than a permissive default that
         * hands one player another player's passing run.
         */
        throw new LogicException(
            'Code Arena submission rules must be resolved against an attempt; '
            .'use attemptSubmissionRules().'
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function attemptSubmissionRules(ChallengeAttempt $attempt): array
    {
        return [
            'run_id' => [
                'required',
                'integer',
                /*
                 * Both halves matter. The attempt scope is the authorization —
                 * run ids are sequential, so without it a player could submit
                 * somebody else's passing run. The status is the S7 guard: a run
                 * the platform declined is not submittable at all, so a capacity
                 * failure can never be graded as a failed answer.
                 */
                Rule::exists('execution_runs', 'id')
                    ->where('challenge_attempt_id', $attempt->id)
                    ->where('status', ExecutionRun::STATUS_FINISHED),
            ],
        ];
    }

    /**
     * What to tell the player, without telling them the answer.
     *
     * Counts and case numbers only. A failing sample can be reasoned about from
     * what they were already shown; a failing hidden case gets its number and
     * its inputs — which the client already has — and never its expectation.
     *
     * @param  array{cases: array<int, array<string, mixed>>, passed: int, total: int}  $graded
     */
    private function feedbackFor(array $graded): string
    {
        $failed = array_values(array_filter(
            $graded['cases'],
            static fn (array $case): bool => $case['passed'] !== true,
        ));

        $timedOut = array_filter($failed, static fn (array $case): bool => $case['status'] === 'timeout');

        $summary = "{$graded['passed']} of {$graded['total']} cases passed.";

        if ($timedOut !== []) {
            // Worth separating from a wrong answer: the code may well be right
            // and too slow, and "wrong" would send them looking in the wrong
            // place.
            return $summary.' '.count($timedOut).' ran out of time.';
        }

        $first = $failed[0] ?? null;

        if ($first === null) {
            return $summary;
        }

        if ($first['status'] === 'error') {
            return $summary.' Case '.$first['case'].' did not return a value.';
        }

        return $summary.' The first failure was case '.$first['case'].'.';
    }
}
