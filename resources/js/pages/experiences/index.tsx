import { Head } from '@inertiajs/react';
import { Plate } from '@/components/rack/plate';
import { Preview } from '@/components/rack/previews';
import { index as experiencesIndex, show } from '@/routes/experiences';
import type { ExperienceCard } from '@/types';

/**
 * The rack.
 *
 * Every plate shares a chassis and previews its own experience through the
 * window in the middle, so the catalogue reads as six different instruments
 * rather than six cards with different words in them. The previews are static
 * fragments, not live data — see `previews.tsx`.
 *
 * `auto-fit` with a 20rem floor rather than fixed breakpoint columns: plates
 * are the same size at every width, and the grid takes as many as fit instead
 * of stretching three of them across a very wide screen.
 */
export default function ExperiencesIndex({
    experiences,
}: {
    experiences: ExperienceCard[];
}) {
    return (
        <>
            <Head title="Experiences" />

            <div className="flex flex-col gap-6">
                <header className="flex flex-col gap-1">
                    <h1 className="text-2xl font-bold">Experiences</h1>
                    <p className="text-muted-foreground max-w-[60ch] text-sm">
                        Each one is a different kind of trouble. Pick whichever
                        looks worst — or don&apos;t choose at all.
                    </p>
                </header>

                {experiences.length === 0 ? (
                    <EmptyRack />
                ) : (
                    <ul className="grid grid-cols-[repeat(auto-fit,minmax(24rem,1fr))] gap-4">
                        {experiences.map((experience) => (
                            <li key={experience.slug} className="min-w-0">
                                <Plate
                                    href={show(experience.slug).url}
                                    name={experience.name}
                                    count={experience.challenges_count}
                                    tagline={experience.tagline}
                                    minutes={experience.estimated_minutes}
                                >
                                    <Preview slug={experience.slug} />
                                </Plate>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

/**
 * An empty screen is an invitation to act, so this one says the command that
 * fills it rather than reporting an absence.
 */
function EmptyRack() {
    return (
        <div className="bg-card border-border border-t-bevel flex flex-col items-center gap-2 rounded-[3px] border px-6 py-12 text-center">
            <p className="font-medium">The rack is empty.</p>
            <p className="text-muted-foreground max-w-[48ch] text-sm">
                No experiences are published yet. Run{' '}
                <code className="bg-face border-border rounded-none border px-1.5 py-0.5 font-mono text-xs">
                    php artisan db:seed
                </code>{' '}
                to load the starting set.
            </p>
        </div>
    );
}

ExperiencesIndex.layout = {
    breadcrumbs: [{ title: 'Experiences', href: experiencesIndex() }],
};
