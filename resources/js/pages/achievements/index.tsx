import { Head } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { index as achievementsIndex } from '@/routes/achievements';

interface AchievementCard {
    key: string | null;
    name: string;
    description: string;
    icon: string | null;
    category: string | null;
    tier: string | null;
    xp_bonus: number | null;
    is_secret: boolean;
    unlocked: boolean;
}

const tierStyles: Record<string, string> = {
    bronze: 'text-amber-700 dark:text-amber-300',
    silver: 'text-slate-600 dark:text-slate-300',
    gold: 'text-yellow-700 dark:text-yellow-300',
};

export default function AchievementsIndex({
    achievements,
    unlocked_count,
}: {
    achievements: AchievementCard[];
    unlocked_count: number;
}) {
    return (
        <>
            <Head title="Achievements" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Achievements
                    </h1>
                    <p className="text-muted-foreground font-mono text-sm">
                        {unlocked_count} of {achievements.length} unlocked
                    </p>
                </header>

                <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {achievements.map((achievement, index) => (
                        <li key={achievement.key ?? `secret-${index}`}>
                            <AchievementTile achievement={achievement} />
                        </li>
                    ))}
                </ul>

                <p className="text-muted-foreground max-w-2xl text-xs">
                    Achievements and levels are gamification. They are not
                    professional qualifications.
                </p>
            </div>
        </>
    );
}

function AchievementTile({ achievement }: { achievement: AchievementCard }) {
    return (
        <Card
            className={cn(
                'h-full',
                // Locked tiles are dimmed, but the text stays legible: an
                // unreadable list of what you could earn is not an incentive.
                !achievement.unlocked && 'border-dashed',
            )}
        >
            <CardContent className="space-y-2 py-4">
                <div className="flex items-baseline justify-between gap-2">
                    <h2 className="font-medium">{achievement.name}</h2>
                    {achievement.tier && (
                        <span
                            className={cn(
                                'font-mono text-[0.7rem] tracking-wide uppercase',
                                tierStyles[achievement.tier] ??
                                    'text-muted-foreground',
                            )}
                        >
                            {achievement.tier}
                        </span>
                    )}
                </div>

                <p className="text-muted-foreground text-sm">
                    {achievement.description}
                </p>

                <div className="flex items-center gap-3 font-mono text-xs">
                    {/*
                     * Not colour alone: the state is spelled out, so it survives
                     * a screen reader and a monochrome display.
                     */}
                    <span
                        className={
                            achievement.unlocked
                                ? 'text-emerald-700 dark:text-emerald-300'
                                : 'text-muted-foreground'
                        }
                    >
                        {achievement.unlocked ? '✓ unlocked' : 'locked'}
                    </span>
                    {achievement.xp_bonus ? (
                        <span className="text-muted-foreground">
                            +{achievement.xp_bonus} XP
                        </span>
                    ) : null}
                </div>
            </CardContent>
        </Card>
    );
}

AchievementsIndex.layout = {
    breadcrumbs: [{ title: 'Achievements', href: achievementsIndex() }],
};
