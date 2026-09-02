import { Head, Link } from '@inertiajs/react';
import { DifficultyBadge } from '@/components/challenge/difficulty-badge';
import { Card, CardContent } from '@/components/ui/card';
import { show as challengeShow } from '@/routes/challenges';

interface Rung {
    level: number;
    title: string;
    xp_required: number;
}

interface Props {
    profile: {
        username: string;
        display_name: string;
        bio: string | null;
        location: string | null;
        website: string | null;
        github_handle: string | null;
        is_public: boolean;
        joined_at: string | null;
    };
    isOwner: boolean;
    detailed: boolean;
    progression: {
        total_xp: number;
        level: Rung;
        next_level: Rung | null;
        progress: number;
        rank: number | null;
    };
    statistics: {
        challenges_completed: number;
        challenges_started: number;
        success_rate: number | null;
        average_solve_seconds: number | null;
        current_streak_days: number;
        longest_streak_days: number;
        experiences_played: number;
    } | null;
    achievements:
        | {
              key: string;
              name: string;
              description: string;
              tier: string | null;
          }[]
        | null;
    recent:
        | {
              challenge: { slug: string; title: string; difficulty: string };
              experience: string;
              solved: boolean;
              completed_at: string | null;
          }[]
        | null;
}

export default function ProfileShow({
    profile,
    isOwner,
    detailed,
    progression,
    statistics,
    achievements,
    recent,
}: Props) {
    return (
        <>
            <Head title={profile.display_name} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="space-y-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {profile.display_name}
                    </h1>
                    <p className="text-muted-foreground font-mono text-sm">
                        @{profile.username}
                        {profile.joined_at && ` · joined ${profile.joined_at}`}
                    </p>
                    {profile.bio && (
                        <p className="max-w-2xl text-sm leading-relaxed">
                            {profile.bio}
                        </p>
                    )}
                    <ProfileLinks profile={profile} />
                </header>

                <Progression progression={progression} />

                {!detailed && (
                    <Card>
                        <CardContent className="py-6 text-center">
                            <p className="font-medium">
                                This profile is private.
                            </p>
                            <p className="text-muted-foreground mx-auto mt-2 max-w-md text-sm">
                                Level and rank stay visible because they already
                                appear on the leaderboard. Activity does not.
                            </p>
                        </CardContent>
                    </Card>
                )}

                {isOwner && !profile.is_public && (
                    <p className="text-muted-foreground font-mono text-xs">
                        Only you can see the detail below. Others see the notice
                        above.
                    </p>
                )}

                {statistics && <Statistics statistics={statistics} />}
                {achievements && <Achievements achievements={achievements} />}
                {recent && <RecentActivity recent={recent} />}

                <p className="text-muted-foreground max-w-2xl text-xs">
                    Levels and titles are gamification. They are not
                    professional qualifications.
                </p>
            </div>
        </>
    );
}

function ProfileLinks({ profile }: { profile: Props['profile'] }) {
    if (!profile.location && !profile.website && !profile.github_handle) {
        return null;
    }

    return (
        <div className="text-muted-foreground flex flex-wrap gap-4 font-mono text-xs">
            {profile.location && <span>{profile.location}</span>}
            {profile.website && (
                <a
                    href={profile.website}
                    // A user-supplied destination: deny it any handle on this tab
                    // and any referrer to follow back.
                    rel="noopener noreferrer nofollow ugc"
                    target="_blank"
                    className="hover:text-foreground focus-visible:ring-ring rounded-sm focus-visible:ring-2 focus-visible:outline-none"
                >
                    {profile.website}
                </a>
            )}
            {profile.github_handle && (
                <a
                    href={`https://github.com/${profile.github_handle}`}
                    rel="noopener noreferrer nofollow ugc"
                    target="_blank"
                    className="hover:text-foreground focus-visible:ring-ring rounded-sm focus-visible:ring-2 focus-visible:outline-none"
                >
                    github.com/{profile.github_handle}
                </a>
            )}
        </div>
    );
}

function Progression({ progression }: { progression: Props['progression'] }) {
    const percent = Math.round(progression.progress * 100);

    return (
        <Card>
            <CardContent className="space-y-3 py-4">
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                    <p className="font-medium">
                        Level {progression.level.level} ·{' '}
                        {progression.level.title}
                    </p>
                    <p className="text-muted-foreground font-mono text-sm tabular-nums">
                        {progression.total_xp.toLocaleString()} XP
                        {progression.rank !== null && ` · #${progression.rank}`}
                    </p>
                </div>

                {progression.next_level ? (
                    <div className="space-y-1">
                        <div
                            className="bg-muted h-2 overflow-hidden rounded-full"
                            role="progressbar"
                            aria-valuenow={percent}
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-label={`Progress to level ${progression.next_level.level}`}
                        >
                            <div
                                className="bg-primary h-full transition-[width] motion-reduce:transition-none"
                                style={{ width: `${percent}%` }}
                            />
                        </div>
                        <p className="text-muted-foreground font-mono text-xs">
                            {progression.next_level.xp_required -
                                progression.total_xp}{' '}
                            XP to {progression.next_level.title}
                        </p>
                    </div>
                ) : (
                    <p className="text-muted-foreground font-mono text-xs">
                        Top of the ladder.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

function Statistics({
    statistics,
}: {
    statistics: NonNullable<Props['statistics']>;
}) {
    const stats: [string, string][] = [
        ['Completed', String(statistics.challenges_completed)],
        ['Attempted', String(statistics.challenges_started)],
        [
            'Success rate',
            // Null and zero mean different things: "no data" is not "never right".
            statistics.success_rate === null
                ? '—'
                : `${Math.round(statistics.success_rate * 100)}%`,
        ],
        [
            'Average solve',
            statistics.average_solve_seconds === null
                ? '—'
                : `${Math.round(statistics.average_solve_seconds / 60)}m`,
        ],
        ['Current streak', `${statistics.current_streak_days}d`],
        ['Longest streak', `${statistics.longest_streak_days}d`],
        ['Experiences', String(statistics.experiences_played)],
    ];

    return (
        <section aria-labelledby="statistics-heading" className="space-y-3">
            <h2
                id="statistics-heading"
                className="font-mono text-xs tracking-widest uppercase"
            >
                Statistics
            </h2>
            <Card>
                <CardContent className="py-4">
                    <dl className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        {stats.map(([label, value]) => (
                            <div key={label} className="space-y-1">
                                <dt className="text-muted-foreground font-mono text-xs">
                                    {label}
                                </dt>
                                <dd className="font-mono text-lg tabular-nums">
                                    {value}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </CardContent>
            </Card>
        </section>
    );
}

function Achievements({
    achievements,
}: {
    achievements: NonNullable<Props['achievements']>;
}) {
    return (
        <section aria-labelledby="achievements-heading" className="space-y-3">
            <h2
                id="achievements-heading"
                className="font-mono text-xs tracking-widest uppercase"
            >
                Achievements
                <span className="text-muted-foreground ml-2 normal-case">
                    ({achievements.length})
                </span>
            </h2>

            {achievements.length === 0 ? (
                <Card>
                    <CardContent className="text-muted-foreground py-6 text-center text-sm">
                        Nothing unlocked yet.
                    </CardContent>
                </Card>
            ) : (
                <ul className="grid gap-3 sm:grid-cols-2">
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
            )}
        </section>
    );
}

function RecentActivity({ recent }: { recent: NonNullable<Props['recent']> }) {
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
                        No finished attempts yet.
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
                                    <div className="flex items-center gap-3 font-mono text-xs">
                                        {/* Spelled out, not carried by colour alone. */}
                                        <span
                                            className={
                                                entry.solved
                                                    ? 'text-emerald-700 dark:text-emerald-300'
                                                    : 'text-muted-foreground'
                                            }
                                        >
                                            {entry.solved ? 'solved' : 'missed'}
                                        </span>
                                        <DifficultyBadge
                                            difficulty={
                                                entry.challenge.difficulty
                                            }
                                        />
                                    </div>
                                </CardContent>
                            </Card>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
