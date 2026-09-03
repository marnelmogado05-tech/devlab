import { Head, Link } from '@inertiajs/react';
import { BoredButton } from '@/components/challenge/bored-button';
import { DifficultyBadge } from '@/components/challenge/difficulty-badge';
import {
    ProgressionCard,
    type Progression,
} from '@/components/profile/progression-card';
import { StatGrid } from '@/components/profile/stat-grid';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { show as attemptShow } from '@/routes/attempts';
import { show as challengeShow } from '@/routes/challenges';
import { show as profileShow } from '@/routes/profiles';

interface OpenAttempt {
    id: number;
    challenge: { slug: string; title: string; difficulty: string };
    experience: string;
    started_at: string;
    expires_at: string;
}

interface FinishedAttempt {
    challenge: { slug: string; title: string; difficulty: string };
    experience: string;
    solved: boolean;
    completed_at: string | null;
}

interface Unlock {
    key: string;
    name: string;
    description: string;
    tier: string | null;
}

interface Props {
    progression: Progression;
    statistics: {
        challenges_completed: number;
        success_rate: number | null;
        current_streak_days: number;
        achievements_unlocked: number;
    };
    openAttempts: OpenAttempt[];
    recent: FinishedAttempt[];
    achievements: Unlock[];
    username: string | null;
}

/**
 * The dashboard.
 *
 * The profile answers "what have I done"; this answers "what do I do now". So
 * the unfinished work comes first, the button second, and the record of past
 * work last — the order someone actually needs it in.
 */
export default function Dashboard({
    progression,
    statistics,
    openAttempts,
    recent,
    achievements,
    username,
}: Props) {
    const started = statistics.challenges_completed > 0 || recent.length > 0;

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {started ? 'Nothing planned?' : 'Welcome to DevLab'}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {started
                                ? 'Let it pick for you. You do not get a say.'
                                : 'Press the button. It picks; you play. That is the whole product.'}
                        </p>
                    </div>

                    <BoredButton />
                </header>

                {openAttempts.length > 0 && (
                    <OpenAttempts attempts={openAttempts} />
                )}

                <ProgressionCard progression={progression} />

                <StatGrid
                    stats={[
                        ['Completed', String(statistics.challenges_completed)],
                        [
                            'Success rate',
                            // "No data" is not "never right".
                            statistics.success_rate === null
                                ? '—'
                                : `${Math.round(statistics.success_rate * 100)}%`,
                        ],
                        [
                            'Current streak',
                            `${statistics.current_streak_days}d`,
                        ],
                        [
                            'Achievements',
                            String(statistics.achievements_unlocked),
                        ],
                    ]}
                />

                <RecentActivity recent={recent} />

                {achievements.length > 0 && (
                    <LatestUnlocks achievements={achievements} />
                )}

                {username && (
                    <p className="text-muted-foreground font-mono text-xs">
                        <Link
                            href={profileShow(username)}
                            className="hover:text-foreground focus-visible:ring-ring rounded-sm focus-visible:ring-2 focus-visible:outline-none"
                        >
                            View your public profile →
                        </Link>
                    </p>
                )}
            </div>
        </>
    );
}

/**
 * Work left open.
 *
 * Above everything, including the button: handing someone a new challenge while
 * an unfinished one quietly counts down is the one thing this page can do that
 * actively wastes their time.
 */
function OpenAttempts({ attempts }: { attempts: OpenAttempt[] }) {
    return (
        <section aria-labelledby="open-heading" className="space-y-3">
            <h2
                id="open-heading"
                className="font-mono text-xs tracking-widest uppercase"
            >
                Still open
            </h2>

            <ul className="space-y-2">
                {attempts.map((attempt) => (
                    <li key={attempt.id}>
                        <Card>
                            <CardContent className="flex flex-wrap items-center justify-between gap-3 py-3">
                                <div className="min-w-0 space-y-1">
                                    <p className="text-sm font-medium">
                                        {attempt.challenge.title}
                                    </p>
                                    <p className="text-muted-foreground flex flex-wrap items-center gap-2 font-mono text-xs">
                                        <span>{attempt.experience}</span>
                                        <DifficultyBadge
                                            difficulty={
                                                attempt.challenge.difficulty
                                            }
                                        />
                                        <Expiry at={attempt.expires_at} />
                                    </p>
                                </div>

                                <Button asChild size="sm">
                                    <Link href={attemptShow(attempt.id)}>
                                        Resume
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    </li>
                ))}
            </ul>
        </section>
    );
}

/**
 * How long is left before the scheduler expires an attempt.
 *
 * Rendered as a `<time>` with the exact instant in `dateTime`, so the rounded
 * text stays readable without becoming the only record of when it actually
 * runs out.
 */
function Expiry({ at }: { at: string }) {
    const minutes = Math.round((new Date(at).getTime() - Date.now()) / 60_000);

    if (minutes <= 0) {
        return <time dateTime={at}>expiring now</time>;
    }

    return (
        <time dateTime={at}>
            {minutes < 60
                ? `${minutes}m left`
                : `${Math.floor(minutes / 60)}h left`}
        </time>
    );
}

function RecentActivity({ recent }: { recent: FinishedAttempt[] }) {
    return (
        <section aria-labelledby="recent-heading" className="space-y-3">
            <h2
                id="recent-heading"
                className="font-mono text-xs tracking-widest uppercase"
            >
                Recent activity
            </h2>

            {recent.length === 0 ? (
                <Card>
                    <CardContent className="text-muted-foreground py-6 text-center text-sm">
                        Nothing finished yet. Press the button.
                    </CardContent>
                </Card>
            ) : (
                <ul className="space-y-2">
                    {recent.map((entry, index) => (
                        <li key={`${entry.challenge.slug}-${index}`}>
                            <Card>
                                <CardContent className="flex flex-wrap items-center justify-between gap-3 py-3">
                                    <div className="min-w-0">
                                        <Link
                                            href={challengeShow(
                                                entry.challenge.slug,
                                            )}
                                            className="focus-visible:ring-ring rounded-sm text-sm font-medium focus-visible:ring-2 focus-visible:outline-none"
                                        >
                                            {entry.challenge.title}
                                        </Link>
                                        <p className="text-muted-foreground font-mono text-xs">
                                            {entry.experience}
                                        </p>
                                    </div>

                                    {/*
                                     * Named, not merely coloured (§44). A tick
                                     * in green and a cross in red carry no
                                     * meaning to a screen reader and none to a
                                     * reader who cannot tell them apart.
                                     */}
                                    <span
                                        className={
                                            entry.solved
                                                ? 'font-mono text-xs text-emerald-700 dark:text-emerald-300'
                                                : 'text-muted-foreground font-mono text-xs'
                                        }
                                    >
                                        {entry.solved ? 'solved' : 'failed'}
                                    </span>
                                </CardContent>
                            </Card>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

function LatestUnlocks({ achievements }: { achievements: Unlock[] }) {
    return (
        <section aria-labelledby="unlocks-heading" className="space-y-3">
            <h2
                id="unlocks-heading"
                className="font-mono text-xs tracking-widest uppercase"
            >
                Latest unlocks
            </h2>

            <ul className="grid gap-3 sm:grid-cols-3">
                {achievements.map((achievement) => (
                    <li key={achievement.key}>
                        <Card>
                            <CardContent className="space-y-1 py-3">
                                <p className="font-medium">
                                    {achievement.name}
                                </p>
                                <p className="text-muted-foreground text-sm">
                                    {achievement.description}
                                </p>
                            </CardContent>
                        </Card>
                    </li>
                ))}
            </ul>
        </section>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
