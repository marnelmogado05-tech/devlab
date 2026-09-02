<?php

declare(strict_types=1);

use App\Events\ChallengeCompleted;
use App\Models\Challenge;
use App\Models\ChallengeAttempt;
use App\Models\Experience;
use App\Models\User;
use App\Services\Challenge\EvaluatorRegistry;
use Illuminate\Support\Facades\Event;
use Tests\Support\FakeEvaluator;

beforeEach(function () {
    FakeEvaluator::reset();

    $this->experience = Experience::factory()->published()->create(['slug' => 'test-experience']);

    app(EvaluatorRegistry::class)->register('test-experience', FakeEvaluator::class);

    $this->challenge = Challenge::factory()->published()->for($this->experience)->create([
        'solution' => ['answer' => 'bool(false)'],
        'explanation' => 'THE-EXPLANATION',
        'difficulty' => 'medium',
        'points' => 100,
        'estimated_minutes' => 10,
    ]);

    $this->user = User::factory()->create();

    $this->attempt = ChallengeAttempt::factory()
        ->for($this->challenge)->for($this->user)
        ->create(['started_at' => now()->subMinute()]);
});

/**
 * @param  array<string, mixed>  $submission
 * @return array<string, mixed>
 */
function payload(array $submission = ['answer' => 'bool(false)']): array
{
    return ['submission' => $submission];
}

it('completes an attempt with a correct answer and scores it', function () {
    $this->actingAs($this->user)
        ->post(route('attempts.submit', $this->attempt), payload())
        ->assertRedirect(route('attempts.show', $this->attempt));

    $this->attempt->refresh();

    expect($this->attempt->status)->toBe(ChallengeAttempt::STATUS_COMPLETED)
        ->and($this->attempt->score)->toBeGreaterThan(0)
        ->and($this->attempt->completed_at)->not->toBeNull()
        ->and($this->attempt->time_taken_seconds)->toBe(60);
});

it('marks a wrong answer failed, not completed', function () {
    // The two must stay distinguishable, or every success-rate figure — and the
    // difficulty calibration built on it — is wrong.
    $this->actingAs($this->user)
        ->post(route('attempts.submit', $this->attempt), payload(['answer' => 'bool(true)']));

    $this->attempt->refresh();

    expect($this->attempt->status)->toBe(ChallengeAttempt::STATUS_FAILED)
        ->and($this->attempt->score)->toBe(0);
});

it('computes the score from server state, not from the request', function () {
    // The obvious attack: send the score you would like to have.
    $this->actingAs($this->user)->post(route('attempts.submit', $this->attempt), [
        'submission' => ['answer' => 'bool(false)'],
        'score' => 99_999,
        'time_taken_seconds' => 1,
        'status' => 'completed',
    ]);

    $this->attempt->refresh();

    expect($this->attempt->score)->toBeLessThan(1_000)
        ->and($this->attempt->time_taken_seconds)->toBe(60);
});

it('scores one submission only, however many times it is sent', function () {
    $this->actingAs($this->user)->post(route('attempts.submit', $this->attempt), payload());
    $score = $this->attempt->refresh()->score;

    // A second submission: a double-click, a retry, a replayed request.
    $this->actingAs($this->user)
        ->post(route('attempts.submit', $this->attempt), payload(['answer' => 'anything else']));

    $this->attempt->refresh();

    expect($this->attempt->score)->toBe($score)
        ->and($this->attempt->status)->toBe(ChallengeAttempt::STATUS_COMPLETED)
        // And the evaluator did not run a second time.
        ->and(FakeEvaluator::$calls)->toBe(1);
});

it('dispatches the completion event exactly once', function () {
    Event::fake([ChallengeCompleted::class]);

    $this->actingAs($this->user)->post(route('attempts.submit', $this->attempt), payload());
    $this->actingAs($this->user)->post(route('attempts.submit', $this->attempt), payload());

    Event::assertDispatchedTimes(ChallengeCompleted::class, 1);
});

it('refuses a submission from anyone but the owner', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('attempts.submit', $this->attempt), payload())
        ->assertForbidden();

    expect($this->attempt->refresh()->isOpen())->toBeTrue();
});

it('refuses a submission to an abandoned attempt', function () {
    $this->attempt->update(['status' => ChallengeAttempt::STATUS_ABANDONED]);

    $this->actingAs($this->user)
        ->post(route('attempts.submit', $this->attempt), payload())
        ->assertForbidden();
});

it('validates the payload against the rules the experience declares', function () {
    $this->actingAs($this->user)
        ->post(route('attempts.submit', $this->attempt), ['submission' => []])
        ->assertSessionHasErrors('submission.answer');

    expect($this->attempt->refresh()->isOpen())->toBeTrue()
        ->and(FakeEvaluator::$calls)->toBe(0);
});

it('withholds the explanation until the attempt is closed', function () {
    $open = $this->actingAs($this->user)->get(route('attempts.show', $this->attempt));

    expect($open->getContent())->not->toContain('THE-EXPLANATION');

    $this->actingAs($this->user)->post(route('attempts.submit', $this->attempt), payload());

    $closed = $this->actingAs($this->user)->get(route('attempts.show', $this->attempt));

    // The payoff, released on completion and not before.
    expect($closed->getContent())->toContain('THE-EXPLANATION');
});

it('stores the score breakdown for a later dispute', function () {
    $this->actingAs($this->user)->post(route('attempts.submit', $this->attempt), payload());

    $evaluation = $this->attempt->refresh()->evaluation;

    expect($evaluation)->toHaveKey('score_breakdown')
        ->and($evaluation['score_breakdown']['total'])->toBe($this->attempt->score);
});
