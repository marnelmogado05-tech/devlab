import { Head, Link } from '@inertiajs/react';
import { DifficultyBadge } from '@/components/challenge/difficulty-badge';
import { Card, CardContent } from '@/components/ui/card';
import {
    index as experiencesIndex,
    show as experienceShow,
} from '@/routes/experiences';
import type { ChallengeDetail } from '@/types';

export default function ChallengeShow({
    challenge,
    experience,
}: {
    challenge: ChallengeDetail;
    experience: { slug: string; name: string };
}) {
    return (
        <>
            <Head title={challenge.title} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <header className="max-w-3xl space-y-3">
                    <Link
                        href={experienceShow(experience.slug)}
                        className="text-muted-foreground hover:text-foreground focus-visible:ring-ring rounded-sm font-mono text-xs focus-visible:ring-2 focus-visible:outline-none"
                    >
                        ← {experience.name}
                    </Link>

                    <h1 className="text-2xl font-semibold tracking-tight">
                        {challenge.title}
                    </h1>

                    <div className="text-muted-foreground flex flex-wrap items-center gap-3 font-mono text-xs">
                        <DifficultyBadge difficulty={challenge.difficulty} />
                        <span>~{challenge.estimated_minutes} min</span>
                        <span>{challenge.points} pts</span>
                        {challenge.tags.map((tag) => (
                            <span key={tag}>#{tag}</span>
                        ))}
                    </div>
                </header>

                <div className="grid max-w-3xl gap-4">
                    <Section title="Briefing">
                        <p className="text-sm leading-relaxed whitespace-pre-line">
                            {challenge.description}
                        </p>
                    </Section>

                    <Section title="Objective">
                        <p className="text-sm leading-relaxed">
                            {challenge.objective}
                        </p>
                    </Section>

                    {challenge.rules && (
                        <Section title="Rules">
                            <p className="text-sm leading-relaxed whitespace-pre-line">
                                {challenge.rules}
                            </p>
                        </Section>
                    )}
                </div>

                {/*
                 * Starting an attempt is the next slice. Saying so plainly beats a
                 * dead button — and the page is genuinely useful as a briefing.
                 */}
                <p className="text-muted-foreground max-w-3xl font-mono text-xs">
                    Attempts are not wired up yet. This is the briefing only.
                </p>
            </div>
        </>
    );
}

function Section({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <Card>
            <CardContent className="space-y-2 py-4">
                <h2 className="font-mono text-xs tracking-widest uppercase">
                    {title}
                </h2>
                {children}
            </CardContent>
        </Card>
    );
}

ChallengeShow.layout = {
    breadcrumbs: [{ title: 'Experiences', href: experiencesIndex() }],
};
