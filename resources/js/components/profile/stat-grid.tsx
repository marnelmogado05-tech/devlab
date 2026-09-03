import { Card, CardContent } from '@/components/ui/card';

/**
 * A row of figures, as a description list.
 *
 * `<dl>` rather than a grid of divs because that is what this is: each figure
 * is a term and its value, and a screen reader announces the pair rather than
 * two loose numbers.
 *
 * Values arrive pre-formatted. Deciding that a null success rate reads "—"
 * rather than "0%" is the caller's business — the two mean different things,
 * and a component that formats numbers cannot know which it was handed.
 */
export function StatGrid({ stats }: { stats: [string, string][] }) {
    return (
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
    );
}
