import { Head, Link } from '@inertiajs/react';
import { BoredButton } from '@/components/challenge/bored-button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index as experiencesIndex, show } from '@/routes/experiences';
import type { ExperienceCard } from '@/types';

export default function ExperiencesIndex({
    experiences,
}: {
    experiences: ExperienceCard[];
}) {
    return (
        <>
            <Head title="Experiences" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Experiences
                        </h1>
                        <p className="text-muted-foreground max-w-2xl text-sm">
                            Each one is a different kind of trouble. Pick
                            whichever looks worst — or don&apos;t choose at all.
                        </p>
                    </div>

                    <BoredButton />
                </header>

                {experiences.length === 0 ? (
                    <EmptyCatalogue />
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {experiences.map((experience) => (
                            <li key={experience.slug}>
                                <ExperienceTile experience={experience} />
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

function ExperienceTile({ experience }: { experience: ExperienceCard }) {
    return (
        <Card className="hover:border-ring/50 relative h-full transition-colors motion-reduce:transition-none">
            <CardHeader>
                <CardTitle>
                    {/*
                     * The whole card is not a click target: a single anchor on the
                     * title keeps one tab stop per card and one predictable
                     * announcement, which a nested-link card does not.
                     */}
                    <Link
                        href={show(experience.slug)}
                        className="focus-visible:ring-ring rounded-sm after:absolute after:inset-0 focus-visible:ring-2 focus-visible:outline-none"
                    >
                        {experience.name}
                    </Link>
                </CardTitle>
            </CardHeader>

            <CardContent className="space-y-4">
                {experience.tagline && (
                    <p className="text-muted-foreground text-sm">
                        {experience.tagline}
                    </p>
                )}

                <dl className="text-muted-foreground flex flex-wrap gap-x-4 gap-y-1 font-mono text-xs">
                    <div className="flex gap-1.5">
                        <dt className="sr-only">Challenges</dt>
                        <dd>
                            {experience.challenges_count}{' '}
                            {experience.challenges_count === 1
                                ? 'challenge'
                                : 'challenges'}
                        </dd>
                    </div>
                    <div className="flex gap-1.5">
                        <dt className="sr-only">Typical length</dt>
                        <dd>~{experience.estimated_minutes} min</dd>
                    </div>
                </dl>
            </CardContent>
        </Card>
    );
}

function EmptyCatalogue() {
    return (
        <Card>
            <CardContent className="py-10 text-center">
                <p className="font-medium">No experiences are published yet.</p>
                <p className="text-muted-foreground mx-auto mt-2 max-w-md text-sm">
                    Run <code className="font-mono">php artisan db:seed</code>{' '}
                    to load the starting set.
                </p>
            </CardContent>
        </Card>
    );
}

ExperiencesIndex.layout = {
    breadcrumbs: [{ title: 'Experiences', href: experiencesIndex() }],
};
