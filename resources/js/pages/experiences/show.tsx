import { Head, Link } from '@inertiajs/react';
import { DifficultyBadge } from '@/components/challenge/difficulty-badge';
import { Card, CardContent } from '@/components/ui/card';
import { show as challengeShow } from '@/routes/challenges';
import { index as experiencesIndex } from '@/routes/experiences';
import type { ChallengeSummary, ExperienceDetail, Paginated } from '@/types';

export default function ExperienceShow({
    experience,
    challenges,
}: {
    experience: ExperienceDetail;
    challenges: Paginated<ChallengeSummary>;
}) {
    return (
        <>
            <Head title={experience.name} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="max-w-3xl space-y-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {experience.name}
                    </h1>
                    {experience.tagline && (
                        <p className="text-muted-foreground">
                            {experience.tagline}
                        </p>
                    )}
                    {experience.description && (
                        <p className="text-sm leading-relaxed">
                            {experience.description}
                        </p>
                    )}
                </header>

                <section
                    aria-labelledby="challenges-heading"
                    className="space-y-3"
                >
                    <h2
                        id="challenges-heading"
                        className="font-mono text-xs tracking-widest uppercase"
                    >
                        Challenges
                        <span className="text-muted-foreground ml-2 normal-case">
                            ({challenges.total})
                        </span>
                    </h2>

                    {challenges.data.length === 0 ? (
                        <NoChallengesYet />
                    ) : (
                        <ul className="space-y-2">
                            {challenges.data.map((challenge) => (
                                <li key={challenge.slug}>
                                    <ChallengeRow challenge={challenge} />
                                </li>
                            ))}
                        </ul>
                    )}

                    {challenges.last_page > 1 && (
                        <Pagination challenges={challenges} />
                    )}
                </section>
            </div>
        </>
    );
}

function ChallengeRow({ challenge }: { challenge: ChallengeSummary }) {
    return (
        <Card className="hover:border-foreground/30 relative transition-colors motion-reduce:transition-none">
            <CardContent className="flex flex-wrap items-center justify-between gap-3 py-4">
                <div className="min-w-0 space-y-1">
                    <Link
                        href={challengeShow(challenge.slug)}
                        className="focus-visible:ring-ring rounded-sm font-medium after:absolute after:inset-0 focus-visible:ring-2 focus-visible:outline-none"
                    >
                        {challenge.title}
                    </Link>
                    {challenge.tags.length > 0 && (
                        <p className="text-muted-foreground truncate font-mono text-xs">
                            {challenge.tags.join(' · ')}
                        </p>
                    )}
                </div>

                <div className="text-muted-foreground flex shrink-0 items-center gap-3 font-mono text-xs">
                    <span>~{challenge.estimated_minutes} min</span>
                    <span>{challenge.points} pts</span>
                    <DifficultyBadge difficulty={challenge.difficulty} />
                </div>
            </CardContent>
        </Card>
    );
}

function NoChallengesYet() {
    return (
        <Card>
            <CardContent className="py-8 text-center">
                <p className="font-medium">Nothing here yet.</p>
                <p className="text-muted-foreground mx-auto mt-2 max-w-md text-sm">
                    This experience is published but has no challenges written
                    for it. Writing one is a genuinely useful contribution.
                </p>
            </CardContent>
        </Card>
    );
}

/** Laravel writes its paginator labels as HTML entities; render them as text. */
function pageLabel(label: string): string {
    return label.replace('&laquo;', '«').replace('&raquo;', '»');
}

function Pagination({
    challenges,
}: {
    challenges: Paginated<ChallengeSummary>;
}) {
    return (
        <nav
            aria-label="Challenge pages"
            className="flex items-center justify-center gap-1 pt-2"
        >
            {challenges.links.map((link, index) =>
                link.url ? (
                    <Link
                        key={index}
                        href={link.url}
                        aria-current={link.active ? 'page' : undefined}
                        className={`focus-visible:ring-ring rounded-md px-3 py-1.5 font-mono text-xs focus-visible:ring-2 focus-visible:outline-none ${
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : 'hover:bg-muted'
                        }`}
                    >
                        {pageLabel(link.label)}
                    </Link>
                ) : (
                    <span
                        key={index}
                        className="text-muted-foreground px-3 py-1.5 font-mono text-xs"
                    >
                        {pageLabel(link.label)}
                    </span>
                ),
            )}
        </nav>
    );
}

ExperienceShow.layout = {
    breadcrumbs: [{ title: 'Experiences', href: experiencesIndex() }],
};
