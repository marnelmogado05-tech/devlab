import { Form, Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { DifficultyBadge } from '@/components/challenge/difficulty-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';
import { destroy, submit } from '@/routes/attempts';
import {
    index as experiencesIndex,
    show as experienceShow,
} from '@/routes/experiences';

interface Attempt {
    id: number;
    status: string;
    started_at: string;
    elapsed_seconds: number;
    challenge_version: number;
}

interface Result {
    status: string;
    correct: boolean;
    feedback: string | null;
    score: number | null;
    max_score: number | null;
    breakdown: Record<string, number> | null;
    time_taken_seconds: number | null;
    explanation: string | null;
}

interface PlayableChallenge {
    slug: string;
    title: string;
    description: string;
    objective: string;
    rules: string | null;
    difficulty: string;
    type: string | null;
    points: number;
    estimated_minutes: number;
    tags: string[];
    configuration: Record<string, unknown>;
}

export default function AttemptShow({
    attempt,
    challenge,
    experience,
    result,
}: {
    attempt: Attempt;
    challenge: PlayableChallenge;
    experience: { slug: string; name: string };
    result: Result | null;
}) {
    const elapsed = useElapsed(
        attempt.elapsed_seconds,
        attempt.status === 'started',
    );

    return (
        <>
            <Head title={challenge.title} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-2">
                        <Link
                            href={experienceShow(experience.slug)}
                            className="text-muted-foreground hover:text-foreground focus-visible:ring-ring rounded-sm font-mono text-xs focus-visible:ring-2 focus-visible:outline-none"
                        >
                            ← {experience.name}
                        </Link>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {challenge.title}
                        </h1>
                        <div className="text-muted-foreground flex flex-wrap items-center gap-3 font-mono text-xs">
                            <DifficultyBadge
                                difficulty={challenge.difficulty}
                            />
                            <span>{challenge.points} pts</span>
                        </div>
                    </div>

                    <ElapsedClock
                        seconds={elapsed}
                        live={attempt.status === 'started'}
                    />
                </header>

                <div className="grid max-w-3xl gap-4">
                    <Card>
                        <CardContent className="space-y-2 py-4">
                            <h2 className="font-mono text-xs tracking-widest uppercase">
                                Objective
                            </h2>
                            <p className="text-sm leading-relaxed">
                                {challenge.objective}
                            </p>
                        </CardContent>
                    </Card>

                    {/*
                     * The experience module renders `configuration` — the snippet,
                     * the logs, the options. Until an experience implements its
                     * interface, showing the raw payload is more honest than an
                     * empty panel, and it makes an authoring mistake visible.
                     */}
                    <Card>
                        <CardContent className="space-y-2 py-4">
                            <h2 className="font-mono text-xs tracking-widest uppercase">
                                Challenge payload
                            </h2>
                            <pre className="bg-muted overflow-x-auto rounded-md p-3 font-mono text-xs">
                                {JSON.stringify(
                                    challenge.configuration,
                                    null,
                                    2,
                                )}
                            </pre>
                            <p className="text-muted-foreground text-xs">
                                No experience interface is wired up yet, so this
                                is the raw payload. Submitting and scoring come
                                next.
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {result ? (
                    <ResultPanel result={result} />
                ) : (
                    <SubmissionForm attemptId={attempt.id} />
                )}
            </div>
        </>
    );
}

/**
 * The generic submission form.
 *
 * Each experience will replace this with its own interface — a multiple choice,
 * a code editor, a Git graph. A single free-text answer is the lowest common
 * denominator that works for every experience until then, and it exercises the
 * real submission path rather than pretending to.
 */
function SubmissionForm({ attemptId }: { attemptId: number }) {
    return (
        <div className="max-w-3xl space-y-3">
            <Form
                action={submit(attemptId)}
                method="post"
                className="space-y-3"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="space-y-2">
                            <Label htmlFor="answer">Your answer</Label>
                            <Input
                                id="answer"
                                name="submission[answer]"
                                autoComplete="off"
                                aria-describedby={
                                    errors['submission.answer']
                                        ? 'answer-error'
                                        : undefined
                                }
                                aria-invalid={
                                    errors['submission.answer']
                                        ? true
                                        : undefined
                                }
                                className="font-mono"
                            />
                            {errors['submission.answer'] && (
                                <p
                                    id="answer-error"
                                    role="alert"
                                    className="text-destructive text-sm"
                                >
                                    {errors['submission.answer']}
                                </p>
                            )}
                        </div>

                        <Button type="submit" disabled={processing}>
                            {processing ? 'Checking…' : 'Submit answer'}
                        </Button>
                    </>
                )}
            </Form>

            <Form action={destroy(attemptId)} method="delete">
                <Button type="submit" variant="ghost" size="sm">
                    Give up
                </Button>
            </Form>
        </div>
    );
}

/**
 * The outcome. A wrong answer is data, not a verdict — the copy says what
 * happened without shaming, per the design language.
 */
function ResultPanel({ result }: { result: Result }) {
    return (
        <div className="max-w-3xl space-y-4" role="status" aria-live="polite">
            <Card>
                <CardContent className="space-y-3 py-4">
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 className="font-mono text-xs tracking-widest uppercase">
                            {result.correct ? 'Solved' : 'Not this time'}
                        </h2>
                        <p className="font-mono text-lg tabular-nums">
                            {result.score} / {result.max_score}
                        </p>
                    </div>

                    {result.feedback && (
                        <p className="text-sm">{result.feedback}</p>
                    )}

                    {result.breakdown && (
                        <dl className="text-muted-foreground grid grid-cols-2 gap-x-6 gap-y-1 font-mono text-xs sm:grid-cols-3">
                            <ScoreLine
                                label="base"
                                value={result.breakdown.base}
                            />
                            <ScoreLine
                                label="speed"
                                value={result.breakdown.speed_bonus}
                            />
                            <ScoreLine
                                label="accuracy"
                                value={result.breakdown.accuracy_bonus}
                            />
                            <ScoreLine
                                label="streak"
                                value={result.breakdown.streak_bonus}
                            />
                            <ScoreLine
                                label="no hint"
                                value={result.breakdown.no_hint_bonus}
                            />
                        </dl>
                    )}
                </CardContent>
            </Card>

            {result.explanation && (
                <Card>
                    <CardContent className="space-y-2 py-4">
                        <h2 className="font-mono text-xs tracking-widest uppercase">
                            Why
                        </h2>
                        <p className="text-sm leading-relaxed whitespace-pre-line">
                            {result.explanation}
                        </p>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

function ScoreLine({ label, value }: { label: string; value: number }) {
    return (
        <div className="flex justify-between gap-2">
            <dt>{label}</dt>
            <dd className="tabular-nums">{value}</dd>
        </div>
    );
}

/**
 * A display-only clock, seeded from the server's elapsed time.
 *
 * It is never sent back and never reaches a score: the server recomputes elapsed
 * time from `started_at` when the attempt closes. A client that pauses this, or
 * edits it, changes nothing but its own screen.
 */
function useElapsed(initial: number, live: boolean) {
    const [seconds, setSeconds] = useState(initial);

    useEffect(() => {
        if (!live) {
            return;
        }

        const id = window.setInterval(() => setSeconds((s) => s + 1), 1000);

        return () => window.clearInterval(id);
    }, [live]);

    return seconds;
}

function ElapsedClock({ seconds, live }: { seconds: number; live: boolean }) {
    const minutes = Math.floor(seconds / 60);
    const remainder = seconds % 60;
    const display = `${String(minutes).padStart(2, '0')}:${String(remainder).padStart(2, '0')}`;

    return (
        <div className="text-right">
            <p
                className="font-mono text-2xl tabular-nums"
                aria-live="off"
                aria-label={`Elapsed time ${minutes} minutes ${remainder} seconds`}
            >
                {display}
            </p>
            <p className="text-muted-foreground font-mono text-xs">
                {live ? 'elapsed' : 'final'}
            </p>
        </div>
    );
}

AttemptShow.layout = {
    breadcrumbs: [{ title: 'Experiences', href: experiencesIndex() }],
};
