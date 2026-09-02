import { Head, Link } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { index as leaderboardsIndex } from '@/routes/leaderboards';

interface Entry {
    rank: number;
    score: number;
    username: string | null;
    display_name: string;
    is_public: boolean;
}

const periodLabels: Record<string, string> = {
    all_time: 'All time',
    weekly: 'This week',
    monthly: 'This month',
};

export default function LeaderboardsIndex({
    period,
    periods,
    entries,
    you,
}: {
    period: string;
    periods: string[];
    entries: Entry[];
    you: { rank: number | null; user_id: number } | null;
}) {
    return (
        <>
            <Head title="Leaderboards" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="space-y-3">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Leaderboards
                    </h1>

                    <nav aria-label="Leaderboard period" className="flex gap-2">
                        {periods.map((option) => (
                            <Link
                                key={option}
                                href={leaderboardsIndex({
                                    query: { period: option },
                                })}
                                aria-current={
                                    option === period ? 'page' : undefined
                                }
                                className={cn(
                                    'focus-visible:ring-ring rounded-md px-3 py-1.5 font-mono text-xs focus-visible:ring-2 focus-visible:outline-none',
                                    option === period
                                        ? 'bg-primary text-primary-foreground'
                                        : 'hover:bg-muted',
                                )}
                            >
                                {periodLabels[option] ?? option}
                            </Link>
                        ))}
                    </nav>

                    {you && (
                        <p className="text-muted-foreground font-mono text-sm">
                            {you.rank
                                ? `You are #${you.rank}.`
                                : 'You are not ranked yet — finish a challenge.'}
                        </p>
                    )}
                </header>

                {entries.length === 0 ? (
                    <Card>
                        <CardContent className="py-10 text-center">
                            <p className="font-medium">Nobody is ranked yet.</p>
                            <p className="text-muted-foreground mx-auto mt-2 max-w-md text-sm">
                                The first person to finish a challenge takes the
                                top spot. It could be you, mostly by default.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardContent className="p-0">
                            <table className="w-full text-sm">
                                <caption className="sr-only">
                                    Ranked players, highest XP first
                                </caption>
                                <thead>
                                    <tr className="text-muted-foreground border-b font-mono text-xs">
                                        <th
                                            scope="col"
                                            className="p-3 text-left"
                                        >
                                            #
                                        </th>
                                        <th
                                            scope="col"
                                            className="p-3 text-left"
                                        >
                                            Player
                                        </th>
                                        <th
                                            scope="col"
                                            className="p-3 text-right"
                                        >
                                            XP
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {entries.map((entry) => (
                                        <tr
                                            key={entry.rank}
                                            className="border-b last:border-0"
                                        >
                                            <td className="p-3 font-mono tabular-nums">
                                                {entry.rank}
                                            </td>
                                            <td className="p-3">
                                                {entry.display_name}
                                            </td>
                                            <td className="p-3 text-right font-mono tabular-nums">
                                                {entry.score.toLocaleString()}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                )}

                <p className="text-muted-foreground max-w-2xl text-xs">
                    Ranks are computed on the server from the XP ledger. Levels
                    and titles are gamification, not professional
                    qualifications.
                </p>
            </div>
        </>
    );
}

LeaderboardsIndex.layout = {
    breadcrumbs: [{ title: 'Leaderboards', href: leaderboardsIndex() }],
};
