import { Head, Link } from '@inertiajs/react';
import { BoredButton } from '@/components/challenge/bored-button';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';
import { index as experiencesIndex } from '@/routes/experiences';

interface Copy {
    title: string;
    description: string;
    /** A dead end is the best possible place to offer someone something to do. */
    offerBored?: boolean;
}

/*
 * Copy says what happened and what the reader can do about it. It never
 * speculates about why, because the honest answer to "why" is usually "we don't
 * know yet" — and a guess reads as either an accusation or an excuse.
 */
const copy: Record<number, Copy> = {
    403: {
        title: 'Not yours to open',
        description:
            'This exists, but your account cannot see it. If a challenge is still in draft, it stays hidden until an editor publishes it.',
    },
    404: {
        title: 'Nothing at this address',
        description:
            'The link may be old, or the challenge may have been unpublished since someone shared it.',
        offerBored: true,
    },
    419: {
        title: 'That page went stale',
        description:
            'Your session expired before the form arrived, so nothing was saved. Sign in again and repeat the last step.',
    },
    429: {
        title: 'Too quick',
        description:
            'You have hit a rate limit. It clears on its own in under a minute — nothing is wrong with your account.',
    },
    500: {
        title: 'That one is on us',
        description:
            'Something failed on the server. The error is recorded; your progress is not affected, because completions are written in a single transaction that either finishes or leaves no trace.',
    },
    503: {
        title: 'Down for maintenance',
        description: 'DevLab is being updated. It will be back shortly.',
    },
};

const fallback: Copy = {
    title: 'Something went wrong',
    description: 'The request could not be completed.',
};

export default function ErrorPage({ status }: { status: number }) {
    const { title, description, offerBored } = copy[status] ?? fallback;

    return (
        <>
            <Head title={title} />

            <div className="flex h-full flex-1 items-center justify-center p-4">
                <div className="max-w-xl space-y-6">
                    <header className="space-y-2">
                        {/*
                         * The number is announced as part of the heading rather
                         * than sitting beside it as decoration: to a screen
                         * reader "404 Nothing at this address" is one useful
                         * sentence, and two separate fragments are not.
                         */}
                        <h1 className="text-2xl font-semibold tracking-tight">
                            <span className="text-muted-foreground font-mono">
                                {status}
                            </span>{' '}
                            {title}
                        </h1>

                        <p className="text-muted-foreground text-sm">
                            {description}
                        </p>
                    </header>

                    <div className="flex flex-wrap items-center gap-3">
                        {offerBored && <BoredButton />}

                        <Button
                            asChild
                            variant={offerBored ? 'outline' : 'default'}
                        >
                            <Link href={experiencesIndex()}>
                                Browse experiences
                            </Link>
                        </Button>

                        <Button asChild variant="ghost">
                            <Link href={home()}>Home</Link>
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}
