import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { ListOrdered, ShieldCheck, Trophy, UserRound, Zap } from 'lucide-react';
import { Face } from '@/components/rack/plate';
import { ThemeCycleButton, ThemeSwitcher } from '@/components/theme-switcher';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';
import { index as experiencesIndex } from '@/routes/experiences';
import type { AuthLayoutProps, AuthVariant } from '@/types';

/**
 * The auth shell.
 *
 * The starter kit shipped three of these — simple, card and split — of which
 * two were dead code and the survivor was a centred column under the Laravel
 * logo. None of them knew anything about DevLab: no wordmark, no plate, no
 * theme control, and a radius that contradicted the rest of the application.
 * Signing in looked like a different product than the one being signed in to.
 *
 * This is the same chassis as the app layout: identical header height,
 * identical 1400px measure, identical wordmark in identical position, so
 * navigating from `/experiences` to `/login` moves nothing that should not
 * move. What the header does NOT carry is the main navigation or the accent
 * button — an auth page has exactly one job, and `BoredButton` is the loudest
 * control in the system. Put it beside a "Log in" submit and the yellow wins,
 * which is the wrong thing to win a login page.
 *
 * Below the header the page is a plate and a window: the form sits on a plate
 * (lighter than the ground, 3px, no shadow — the rack's rules, unchanged), and
 * beside it the same recessed {@link Face} the experience plates preview
 * themselves through. Reusing that component rather than imitating it is the
 * point — the auth screen is built from the catalogue's vocabulary rather than
 * a lookalike of it.
 *
 * The window is hidden below `lg`. It is context, not content: every word the
 * form needs is in the form.
 */
export default function AuthRailLayout({
    children,
    title,
    description,
    variant = 'guest',
}: AuthLayoutProps) {
    return (
        <div className="bg-background flex min-h-dvh flex-col">
            <AuthRail />

            <main className="mx-auto flex w-full max-w-5xl flex-1 items-center px-4 py-10 sm:px-6 lg:py-16">
                <div className="grid w-full gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,26rem)] lg:items-center lg:gap-16">
                    <Aside variant={variant} />

                    {/*
                     * The plate. `border-t-bevel` is the lighter top edge doing
                     * the job a drop shadow would do elsewhere — the one
                     * separation device this system allows.
                     */}
                    <div className="bg-card border-border border-t-bevel mx-auto w-full max-w-[26rem] rounded-[3px] border p-6 sm:p-8 lg:max-w-none">
                        {title && (
                            <h1 className="text-xl font-semibold tracking-tight">
                                {title}
                            </h1>
                        )}

                        {description && (
                            <p className="text-muted-foreground mt-2 text-sm">
                                {description}
                            </p>
                        )}

                        <div className={title || description ? 'mt-6' : ''}>
                            {children}
                        </div>
                    </div>
                </div>
            </main>

            <footer className="text-muted-foreground border-border border-t px-4 py-6 text-center font-mono text-xs sm:px-6">
                Open source, MIT licensed. Levels and titles are gamification,
                not professional qualifications.
            </footer>
        </div>
    );
}

/**
 * The rail, minus the instruments.
 *
 * Same 3.5rem, same sticky blur, same measure, same wordmark — and then only
 * the two controls that are useful mid-sign-in: a way to change the theme, and
 * a way out to the catalogue. The theme control matters more here than
 * anywhere, because the settings page that used to hold it is gone and a
 * signed-out visitor has no other surface to reach it from.
 */
function AuthRail() {
    return (
        <header className="bg-background/92 border-border sticky top-0 z-40 border-b backdrop-blur-md">
            <div className="mx-auto flex h-14 max-w-[1400px] items-center justify-between gap-4 px-4 sm:px-6">
                <Link
                    href={home()}
                    prefetch
                    className="focus-visible:ring-ring rounded-sm font-mono text-sm font-bold tracking-tight focus-visible:ring-2 focus-visible:outline-none"
                >
                    dev<span className="text-primary">/</span>lab
                </Link>

                <div className="flex items-center gap-2">
                    <ThemeSwitcher className="hidden sm:inline-flex" />
                    <ThemeCycleButton className="sm:hidden" />

                    <Button asChild variant="ghost" size="sm">
                        <Link href={experiencesIndex()}>Browse</Link>
                    </Button>
                </div>
            </div>
        </header>
    );
}

type AsideCopy = {
    heading: string;
    body: string;
    command: string;
    rows: { key: string; value: string }[];
    points: { icon: LucideIcon; label: string }[];
    note: string;
};

/*
 * Two states, because one pitch is wrong on half of these pages. "Sign in to
 * keep your XP" is the right thing to say beside a login form and a strange
 * thing to say to someone already signed in who is being asked to confirm a
 * password.
 *
 * Nothing here states a number. The landing page reads its counts at request
 * time precisely so the copy cannot drift from reality, and baking "50
 * challenges" into a layout would undo that from the other end.
 */
const asides: Record<AuthVariant, AsideCopy> = {
    guest: {
        heading: 'An account is only somewhere to keep the score.',
        body: 'Every experience, every challenge and every leaderboard is readable without one. Signing in is what makes the numbers stick.',
        command: 'devlab whoami',
        rows: [
            { key: 'user', value: 'guest' },
            { key: 'xp', value: 'not recorded' },
            { key: 'achievements', value: 'not recorded' },
            { key: 'leaderboard', value: 'unranked' },
        ],
        points: [
            { icon: Zap, label: 'XP that survives the tab closing' },
            { icon: Trophy, label: 'Achievements that unlock once and stay' },
            { icon: ListOrdered, label: 'A place on the leaderboards' },
            { icon: UserRound, label: 'A public profile, if you want one' },
        ],
        note: 'Levels and titles are gamification, not qualifications.',
    },
    secure: {
        heading: 'A checkpoint, not a wall.',
        body: 'Anything that touches your account asks again first. Your progress is untouched while you sort this out.',
        command: 'devlab session',
        rows: [
            { key: 'state', value: 'awaiting confirmation' },
            { key: 'progress', value: 'untouched' },
            { key: 'xp ledger', value: 'append-only' },
        ],
        points: [
            {
                icon: ShieldCheck,
                label: 'Nothing here can subtract from your XP',
            },
        ],
        note: 'XP is an append-only ledger. There is no undo, because there is nothing to undo.',
    },
};

function Aside({ variant }: { variant: AuthVariant }) {
    const copy = asides[variant];

    return (
        <aside className="hidden lg:block">
            <h2 className="text-3xl font-semibold tracking-tight text-balance">
                {copy.heading}
            </h2>

            <p className="text-muted-foreground mt-4 max-w-md text-balance">
                {copy.body}
            </p>

            {/*
             * Monospace means a machine said it, and a shell transcript is the
             * one thing on this page that qualifies — so it gets the recessed
             * window and everything around it stays in prose. `Face` is already
             * `aria-hidden`; the heading and the list above and below it carry
             * the same information in text.
             */}
            <Face className="mt-8 max-w-md">
                <p>
                    <span className="text-muted-foreground">$ </span>
                    {copy.command}
                </p>

                <dl className="mt-2 grid grid-cols-[9rem_1fr] gap-x-4">
                    {copy.rows.map((row) => (
                        <div key={row.key} className="contents">
                            <dt className="text-muted-foreground">{row.key}</dt>
                            <dd>{row.value}</dd>
                        </div>
                    ))}
                </dl>

                {/*
                 * A resting cursor, not a blinking one. Motion in this system is
                 * feedback; a cursor that pulses on a login page is decoration
                 * wearing a terminal costume.
                 */}
                <p className="mt-2">
                    <span className="text-muted-foreground">$ </span>
                    <span className="bg-foreground/70 inline-block h-[1.05em] w-[0.55em] translate-y-[0.15em]" />
                </p>
            </Face>

            <ul className="mt-8 max-w-md space-y-3">
                {copy.points.map((point) => (
                    <li
                        key={point.label}
                        className="text-muted-foreground flex items-start gap-3 text-sm"
                    >
                        <point.icon
                            className="text-foreground mt-0.5 size-4 shrink-0"
                            aria-hidden
                        />
                        {point.label}
                    </li>
                ))}
            </ul>

            <p className="text-muted-foreground mt-8 max-w-md font-mono text-xs">
                {copy.note}
            </p>
        </aside>
    );
}
