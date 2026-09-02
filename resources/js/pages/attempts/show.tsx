import { Form, Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { DifficultyBadge } from '@/components/challenge/difficulty-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { destroy } from '@/routes/attempts';
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
}: {
    attempt: Attempt;
    challenge: PlayableChallenge;
    experience: { slug: string; name: string };
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

                {attempt.status === 'started' && (
                    <Form
                        action={destroy(attempt.id)}
                        method="delete"
                        className="max-w-3xl"
                    >
                        <Button type="submit" variant="outline">
                            Give up
                        </Button>
                    </Form>
                )}
            </div>
        </>
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
