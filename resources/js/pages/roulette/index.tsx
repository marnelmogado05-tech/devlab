import { Form, Head, Link } from '@inertiajs/react';
import { DifficultyBadge } from '@/components/challenge/difficulty-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { bored, login } from '@/routes';
import { store } from '@/routes/attempts';
import { show as challengeShow } from '@/routes/challenges';

interface Assignment {
    slug: string;
    title: string;
    description: string;
    difficulty: string;
    estimated_minutes: number;
    points: number;
    tags: string[];
    experience: {
        slug: string;
        name: string;
        tagline: string | null;
        icon: string | null;
    };
}

/**
 * Dev Roulette: the moment between pressing the button and starting something.
 *
 * The copy is deliberately not a choice. "You have been assigned" rather than
 * "we recommend" — being handed something you would not have picked is the
 * mechanic, and asking politely undercuts it.
 */
export default function Roulette({
    assignment,
    signedIn,
}: {
    assignment: Assignment;
    signedIn: boolean;
}) {
    return (
        <>
            <Head title="I'm Bored" />

            <div className="flex h-full flex-1 flex-col items-center justify-center p-4">
                <div className="w-full max-w-xl space-y-6">
                    <p
                        className="text-muted-foreground text-center font-mono text-xs tracking-widest uppercase"
                        // Announced on arrival: the assignment is the content of
                        // this page, not decoration around it.
                        role="status"
                    >
                        You have been assigned
                    </p>

                    <Card>
                        <CardContent className="space-y-5 py-6">
                            <div className="space-y-1 text-center">
                                <Link
                                    href={challengeShow(assignment.slug)}
                                    className="focus-visible:ring-ring rounded-sm text-2xl font-semibold tracking-tight focus-visible:ring-2 focus-visible:outline-none"
                                >
                                    {assignment.title}
                                </Link>
                                <p className="text-muted-foreground font-mono text-sm">
                                    {assignment.experience.name}
                                </p>
                            </div>

                            <p className="text-center text-sm leading-relaxed">
                                {assignment.description}
                            </p>

                            <dl className="text-muted-foreground flex flex-wrap items-center justify-center gap-x-6 gap-y-2 font-mono text-xs">
                                <div className="flex items-center gap-2">
                                    <dt>Difficulty</dt>
                                    <dd>
                                        <DifficultyBadge
                                            difficulty={assignment.difficulty}
                                        />
                                    </dd>
                                </div>
                                <div className="flex items-center gap-2">
                                    <dt>Time</dt>
                                    <dd>~{assignment.estimated_minutes} min</dd>
                                </div>
                                <div className="flex items-center gap-2">
                                    <dt>Worth</dt>
                                    <dd>{assignment.points} pts</dd>
                                </div>
                            </dl>

                            <div className="flex flex-col items-center gap-3">
                                {signedIn ? (
                                    <Form
                                        action={store(assignment.slug)}
                                        method="post"
                                        className="w-full sm:w-auto"
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                size="lg"
                                                disabled={processing}
                                                className="w-full font-mono sm:w-auto"
                                            >
                                                {processing
                                                    ? 'Starting…'
                                                    : 'Start'}
                                            </Button>
                                        )}
                                    </Form>
                                ) : (
                                    <Button
                                        asChild
                                        size="lg"
                                        className="w-full font-mono sm:w-auto"
                                    >
                                        <Link href={login()}>
                                            Sign in to start
                                        </Link>
                                    </Button>
                                )}

                                {/*
                                 * Refusing an assignment and taking another is
                                 * part of the mechanic, not a failure of it — so
                                 * it gets a control rather than a back button.
                                 */}
                                <Link
                                    href={bored()}
                                    prefetch={false}
                                    className="text-muted-foreground hover:text-foreground focus-visible:ring-ring rounded-sm font-mono text-xs focus-visible:ring-2 focus-visible:outline-none"
                                >
                                    Not that. Spin again.
                                </Link>
                            </div>
                        </CardContent>
                    </Card>

                    {!signedIn && (
                        <p className="text-muted-foreground text-center font-mono text-xs">
                            Browsing is open to everyone. Attempts are recorded,
                            so they need an account.
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}
