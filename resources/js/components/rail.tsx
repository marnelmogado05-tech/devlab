import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, Menu } from 'lucide-react';
import { useState } from 'react';
import { BoredButton } from '@/components/challenge/bored-button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { bored, dashboard, home, login, register } from '@/routes';
import { index as achievementsIndex } from '@/routes/achievements';
import {
    index as experiencesIndex,
    show as experienceShow,
} from '@/routes/experiences';
import { index as leaderboardsIndex } from '@/routes/leaderboards';

/**
 * The top rail — DevLab's navigation.
 *
 * This replaced a collapsible left sidebar. The sidebar worked, but §46 is
 * explicit that DevLab must not read as "a generic admin dashboard", and a
 * fixed left rail of grouped links is the single strongest signal of one. It
 * also spent 16rem of horizontal room on every page, which the experiences that
 * actually need width — the Git graph, the system-design canvas, a diff — were
 * then squeezed out of.
 *
 * What survives from the sidebar is its ordering argument: "I'm Bored" is the
 * product and everything else is a way of not pressing it, so the button sits
 * at the end of the rail where the eye lands last and is the only thing wearing
 * the accent.
 *
 * The per-experience list moved into a dropdown rather than being flattened
 * into the rail. Six top-level links would crowd out the one that matters, and
 * the catalogue page is a better browsing surface than a menu is.
 */
export function Rail() {
    const page = usePage();
    const { auth, navExperiences } = page.props;

    const signedIn = auth.user !== null;

    const links = [
        { title: 'Experiences', href: experiencesIndex().url },
        { title: 'Achievements', href: achievementsIndex().url },
        { title: 'Leaderboards', href: leaderboardsIndex().url },
        ...(signedIn ? [{ title: 'Dashboard', href: dashboard().url }] : []),
    ];

    return (
        <header className="bg-background/92 border-border sticky top-0 z-40 border-b backdrop-blur-md">
            <div className="mx-auto flex h-14 max-w-[1400px] items-center gap-6 px-4 sm:px-6">
                <Link
                    href={signedIn ? dashboard() : home()}
                    prefetch
                    className="focus-visible:ring-ring rounded-sm font-mono text-sm font-bold tracking-tight focus-visible:ring-2 focus-visible:outline-none"
                >
                    dev<span className="text-primary">/</span>lab
                </Link>

                <nav
                    aria-label="Main"
                    className="hidden items-center gap-5 md:flex"
                >
                    {links.map((link) => (
                        <RailLink
                            key={link.href}
                            href={link.href}
                            active={isActive(page.url, link.href)}
                        >
                            {link.title}
                        </RailLink>
                    ))}

                    {navExperiences.length > 0 && (
                        <DropdownMenu>
                            <DropdownMenuTrigger className="text-muted-foreground hover:text-foreground focus-visible:ring-ring flex items-center gap-1 rounded-sm border-b border-transparent py-0.5 text-sm focus-visible:ring-2 focus-visible:outline-none">
                                Jump to
                                <ChevronDown className="size-3.5" aria-hidden />
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start">
                                {navExperiences.map((experience) => (
                                    <DropdownMenuItem
                                        key={experience.slug}
                                        asChild
                                    >
                                        <Link
                                            href={experienceShow(
                                                experience.slug,
                                            )}
                                        >
                                            {experience.name}
                                        </Link>
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </nav>

                <div className="ml-auto flex items-center gap-2">
                    {signedIn ? (
                        <UserButton />
                    ) : (
                        <div className="hidden items-center gap-4 pr-1 sm:flex">
                            <RailLink href={login().url} active={false}>
                                Log in
                            </RailLink>
                            <RailLink href={register().url} active={false}>
                                Sign up
                            </RailLink>
                        </div>
                    )}

                    <BoredButton />

                    <MobileMenu links={links} signedIn={signedIn} />
                </div>
            </div>
        </header>
    );
}

/**
 * A rail link.
 *
 * The active state is a solid underline rather than a filled pill, because a
 * filled pill in this system means "press me" and only one thing gets to say
 * that. Hover borrows the same underline at a quieter colour, so the affordance
 * and the state are the same shape.
 */
function RailLink({
    href,
    active,
    children,
}: {
    href: string;
    active: boolean;
    children: React.ReactNode;
}) {
    return (
        <Link
            href={href}
            aria-current={active ? 'page' : undefined}
            className={cn(
                'focus-visible:ring-ring rounded-sm border-b py-0.5 text-sm transition-colors focus-visible:ring-2 focus-visible:outline-none',
                active
                    ? 'text-foreground border-foreground'
                    : 'text-muted-foreground hover:text-foreground hover:border-border border-transparent',
            )}
        >
            {children}
        </Link>
    );
}

/**
 * The account control: an avatar, and nothing else.
 *
 * The sidebar could afford a name and an email beside it. A 3.5rem rail cannot,
 * and the name is one press away in the menu — so the rail shows the one thing
 * that is recognisable at 32px and leaves the rest to the dropdown.
 */
function UserButton() {
    const { auth } = usePage().props;
    const getInitials = useInitials();

    if (auth.user === null) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger className="hover:bg-accent focus-visible:ring-ring flex size-11 items-center justify-center rounded-sm focus-visible:ring-2 focus-visible:outline-none">
                <Avatar className="size-8 overflow-hidden rounded-full">
                    <AvatarImage
                        src={auth.user.avatar}
                        alt=""
                        aria-hidden="true"
                    />
                    <AvatarFallback className="bg-secondary text-secondary-foreground text-xs font-medium">
                        {getInitials(auth.user.name)}
                    </AvatarFallback>
                </Avatar>
                <span className="sr-only">Account: {auth.user.name}</span>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
                <UserMenuContent user={auth.user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/**
 * The rail below `md`.
 *
 * The wordmark and the button stay visible at every width — losing the product's
 * one control behind a hamburger would be the wrong thing to hide — and only the
 * navigation collapses.
 */
function MobileMenu({
    links,
    signedIn,
}: {
    links: { title: string; href: string }[];
    signedIn: boolean;
}) {
    const [open, setOpen] = useState(false);
    const { navExperiences } = usePage().props;

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger className="hover:bg-accent focus-visible:ring-ring flex size-11 items-center justify-center rounded-sm focus-visible:ring-2 focus-visible:outline-none md:hidden">
                <Menu className="size-5" aria-hidden />
                <span className="sr-only">Open navigation</span>
            </SheetTrigger>

            <SheetContent side="right" className="w-72">
                <SheetHeader>
                    <SheetTitle className="font-mono text-sm">
                        Navigation
                    </SheetTitle>
                </SheetHeader>

                <nav
                    aria-label="Main"
                    className="flex flex-col gap-1 px-4 pb-6"
                >
                    {links.map((link) => (
                        <Link
                            key={link.href}
                            href={link.href}
                            onClick={() => setOpen(false)}
                            className="hover:bg-accent focus-visible:ring-ring flex min-h-11 items-center rounded-sm px-3 text-sm focus-visible:ring-2 focus-visible:outline-none"
                        >
                            {link.title}
                        </Link>
                    ))}

                    {!signedIn && (
                        <>
                            <Link
                                href={login()}
                                onClick={() => setOpen(false)}
                                className="hover:bg-accent flex min-h-11 items-center rounded-sm px-3 text-sm"
                            >
                                Log in
                            </Link>
                            <Link
                                href={register()}
                                onClick={() => setOpen(false)}
                                className="hover:bg-accent flex min-h-11 items-center rounded-sm px-3 text-sm"
                            >
                                Sign up
                            </Link>
                        </>
                    )}

                    {navExperiences.length > 0 && (
                        <>
                            <span className="text-muted-foreground mt-4 px-3 font-mono text-xs">
                                Experiences
                            </span>
                            {navExperiences.map((experience) => (
                                <Link
                                    key={experience.slug}
                                    href={experienceShow(experience.slug)}
                                    onClick={() => setOpen(false)}
                                    className="hover:bg-accent focus-visible:ring-ring flex min-h-11 items-center rounded-sm px-3 text-sm focus-visible:ring-2 focus-visible:outline-none"
                                >
                                    {experience.name}
                                </Link>
                            ))}
                        </>
                    )}
                </nav>
            </SheetContent>
        </Sheet>
    );
}

/**
 * Whether a rail link points at the page being viewed.
 *
 * Prefix matching, so `/experiences/cursed-code` still lights "Experiences" —
 * but "/" is excluded from that, or the wordmark's target would match every
 * page in the application.
 */
function isActive(currentUrl: string, href: string): boolean {
    const path = currentUrl.split('?')[0];

    if (href === bored().url) {
        return path === href;
    }

    return path === href || path.startsWith(`${href}/`);
}
