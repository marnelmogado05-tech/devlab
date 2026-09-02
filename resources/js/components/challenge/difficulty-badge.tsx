import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

/**
 * Difficulty, as a badge.
 *
 * Colour is never the only carrier of state (§44), so the label is always
 * present and the colour merely reinforces it. Each pair is chosen to clear
 * 4.5:1 against its own background in both themes — dim grey on near-black is
 * the standard dark-theme failure and these are deliberately not that.
 */
const styles: Record<string, string> = {
    easy: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
    medium: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    hard: 'bg-orange-600/15 text-orange-700 dark:text-orange-300',
    expert: 'bg-rose-600/15 text-rose-700 dark:text-rose-300',
};

export function DifficultyBadge({
    difficulty,
    className,
}: {
    difficulty: string;
    className?: string;
}) {
    return (
        <Badge
            variant="outline"
            className={cn(
                'border-transparent font-mono text-[0.7rem] tracking-wide uppercase',
                styles[difficulty] ?? 'bg-muted text-muted-foreground',
                className,
            )}
        >
            {difficulty}
        </Badge>
    );
}
