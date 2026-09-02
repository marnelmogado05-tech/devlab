import { Form, Head, Link, usePage } from '@inertiajs/react';
import { DifficultyBadge } from '@/components/challenge/difficulty-badge';
import { ReportChallenge } from '@/components/challenge/report-challenge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { store } from '@/routes/attempts';
import {
    index as experiencesIndex,
    show as experienceShow,
} from '@/routes/experiences';
import { login } from '@/routes';
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

                <div className="flex max-w-3xl flex-wrap items-center justify-between gap-4">
                    <StartAttempt challenge={challenge} />
                    <ReportControl slug={challenge.slug} />
                </div>
            </div>
        </>
    );
}

/**
 * Reporting needs an account, like every other write. A guest sees nothing rather
 * than a control that would bounce them to a sign-in page.
 */
function ReportControl({
    slug,
    attemptId = null,
}: {
    slug: string;
    attemptId?: number | null;
}) {
    const page = usePage().props;

    if (!page.auth?.user) {
        return null;
    }

    return (
        <ReportChallenge
            challengeSlug={slug}
            attemptId={attemptId}
            reasons={page.reportReasons}
            reasonsNeedingDetails={page.reportReasonsNeedingDetails}
        />
    );
}

/**
 * Browsing is public, so this button has two audiences. A guest is sent to sign
 * in rather than shown a control that would fail — an attempt belongs to
 * somebody, and anonymous progress cannot be awarded.
 */
function StartAttempt({ challenge }: { challenge: ChallengeDetail }) {
    const user = usePage().props.auth?.user;

    if (!user) {
        return (
            <div className="max-w-3xl space-y-2">
                <Button asChild>
                    <Link href={login()}>Sign in to attempt this</Link>
                </Button>
                <p className="text-muted-foreground font-mono text-xs">
                    Browsing is open to everyone. Attempts are recorded, so they
                    need an account.
                </p>
            </div>
        );
    }

    return (
        <Form
            action={store(challenge.slug)}
            method="post"
            className="max-w-3xl"
        >
            {({ processing }) => (
                <Button type="submit" disabled={processing}>
                    {processing ? 'Starting…' : 'Start attempt'}
                </Button>
            )}
        </Form>
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
