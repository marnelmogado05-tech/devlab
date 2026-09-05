import { Head, Link, usePage } from '@inertiajs/react';
import { BoredButton } from '@/components/challenge/bored-button';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
import {
    index as experiencesIndex,
    show as experienceShow,
} from '@/routes/experiences';

interface ExperienceTeaser {
    slug: string;
    name: string;
    tagline: string | null;
    challenges_count: number;
}

/**
 * The landing page.
 *
 * Dark-first, terminal-inspired, and built around one control. DevLab's pitch is
 * "open it, press the button, get handed something" — so the button is the page,
 * and everything else is there to make pressing it feel worth doing.
 *
 * The counts are real. Claiming more content than exists is the kind of lie that
 * costs a contributor an evening.
 */
export default function Welcome({
    experiences,
    challengeCount,
}: {
    experiences: ExperienceTeaser[];
    challengeCount: number;
}) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="An open-source developer playground" />

            <div className="bg-background text-foreground flex min-h-dvh flex-col">
                <TopBar signedIn={auth.user !== null} />

                <main className="flex flex-1 flex-col items-center justify-center px-6 py-16">
                    <div className="w-full max-w-3xl space-y-12">
                        <Hero challengeCount={challengeCount} />
                        <Experiences experiences={experiences} />
                        <Pitch />
                    </div>
                </main>

                <footer className="text-muted-foreground border-t px-6 py-6 text-center font-mono text-xs">
                    Open source, MIT licensed. Levels and titles are
                    gamification, not professional qualifications.
                </footer>
            </div>
        </>
    );
}

function TopBar({ signedIn }: { signedIn: boolean }) {
    return (
        <header className="flex items-center justify-between px-6 py-5">
            {/* The same wordmark the rail wears, so the landing page and the
                application are recognisably one product. */}
            <span className="font-mono text-sm font-bold tracking-tight">
                dev<span className="text-primary">/</span>lab
            </span>

            <nav className="flex items-center gap-2 text-sm">
                <Button asChild variant="ghost" size="sm">
                    <Link href={experiencesIndex()}>Browse</Link>
                </Button>

                {signedIn ? (
                    <Button asChild variant="outline" size="sm">
                        <Link href={dashboard()}>Dashboard</Link>
                    </Button>
                ) : (
                    <>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={login()}>Log in</Link>
                        </Button>
                        <Button asChild variant="outline" size="sm">
                            <Link href={register()}>Sign up</Link>
                        </Button>
                    </>
                )}
            </nav>
        </header>
    );
}

function Hero({ challengeCount }: { challengeCount: number }) {
    return (
        <div className="space-y-6 text-center">
            <p className="text-muted-foreground font-mono text-xs tracking-widest uppercase">
                An open-source developer playground
            </p>

            <h1 className="text-4xl font-semibold tracking-tight text-balance sm:text-5xl">
                You have nothing to do.
                <br />
                <span className="text-muted-foreground">We can fix that.</span>
            </h1>

            <p className="text-muted-foreground mx-auto max-w-xl text-balance">
                Cursed snippets, planted bugs, and code that does not do what it
                says. Press the button and take whatever you are given.
            </p>

            <div className="flex flex-col items-center gap-3 pt-2">
                <BoredButton className="px-10 text-base" />

                <p className="text-muted-foreground font-mono text-xs">
                    {challengeCount > 0 ? (
                        <>
                            {challengeCount} challenge
                            {challengeCount === 1 ? '' : 's'} waiting · no
                            account needed to look
                        </>
                    ) : (
                        <>No challenges published yet — have a browse anyway</>
                    )}
                </p>
            </div>
        </div>
    );
}

function Experiences({ experiences }: { experiences: ExperienceTeaser[] }) {
    if (experiences.length === 0) {
        return null;
    }

    return (
        <section aria-labelledby="experiences-heading" className="space-y-4">
            <h2
                id="experiences-heading"
                className="text-muted-foreground text-center font-mono text-xs tracking-widest uppercase"
            >
                What you might get
            </h2>

            <ul className="grid gap-3 sm:grid-cols-2">
                {experiences.map((experience) => (
                    <li key={experience.slug}>
                        <Link
                            href={experienceShow(experience.slug)}
                            className="hover:border-ring/50 focus-visible:ring-ring block h-full rounded-lg border p-4 transition-colors focus-visible:ring-2 focus-visible:outline-none motion-reduce:transition-none"
                        >
                            <p className="font-medium">{experience.name}</p>
                            {experience.tagline && (
                                <p className="text-muted-foreground mt-1 text-sm">
                                    {experience.tagline}
                                </p>
                            )}
                            <p className="text-muted-foreground mt-2 font-mono text-xs">
                                {experience.challenges_count} challenge
                                {experience.challenges_count === 1 ? '' : 's'}
                            </p>
                        </Link>
                    </li>
                ))}
            </ul>
        </section>
    );
}

function Pitch() {
    return (
        <section className="space-y-4 text-center">
            <p className="text-muted-foreground text-sm">
                The ideal reaction is
            </p>

            {/*
             * The README's own framing, which is the clearest statement of what
             * this is for that the project has.
             */}
            <blockquote className="space-y-3">
                <p className="font-mono text-sm">
                    &ldquo;I only opened this because I was bored.&rdquo;
                </p>
                <p className="text-muted-foreground font-mono text-xs">
                    followed, forty-five minutes later, by
                </p>
                <p className="font-mono text-sm">
                    &ldquo;Why am I still debugging a fake production
                    server?&rdquo;
                </p>
            </blockquote>
        </section>
    );
}
