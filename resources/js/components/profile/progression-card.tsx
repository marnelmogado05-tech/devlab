import { Card, CardContent } from '@/components/ui/card';

export interface Rung {
    level: number;
    title: string;
    xp_required: number;
}

export interface Progression {
    total_xp: number;
    level: Rung;
    next_level: Rung | null;
    progress: number;
    rank: number | null;
}

/**
 * Level, XP, rank and the distance to the next rung.
 *
 * Shared by the profile and the dashboard. The same numbers shown two ways
 * would eventually round two ways, and a player who sees a different level on
 * two pages has no reason to trust either.
 */
export function ProgressionCard({ progression }: { progression: Progression }) {
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
