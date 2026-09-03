import { Link, usePage } from '@inertiajs/react';
import {
    Dices,
    LayoutGrid,
    Library,
    LogIn,
    Medal,
    Trophy,
    UserPlus,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { experienceIcon } from '@/lib/experience-icon';
import { bored, dashboard, home, login, register } from '@/routes';
import { index as achievementsIndex } from '@/routes/achievements';
import {
    index as experiencesIndex,
    show as experienceShow,
} from '@/routes/experiences';
import { index as leaderboardsIndex } from '@/routes/leaderboards';
import type { NavItem } from '@/types';

/*
 * "I'm Bored" is first, alone, and above everything else, because it is the
 * product. Every other link is a way of not pressing it.
 */
const playNavItems: NavItem[] = [
    {
        title: "I'm Bored",
        href: bored(),
        icon: Dices,
    },
    {
        title: 'All experiences',
        href: experiencesIndex(),
        icon: Library,
    },
];

/*
 * Progress, which only means anything once there is some. Achievements and
 * leaderboards are public — a visitor can see what there is to earn before
 * deciding to sign up, which is the whole argument for a public catalogue.
 */
const progressNavItems: NavItem[] = [
    {
        title: 'Achievements',
        href: achievementsIndex(),
        icon: Medal,
    },
    {
        title: 'Leaderboards',
        href: leaderboardsIndex(),
        icon: Trophy,
    },
];

export function AppSidebar() {
    const { auth, navExperiences } = usePage().props;

    const signedIn = auth.user !== null;

    /*
     * The experiences come from the published catalogue rather than a list kept
     * here, so authoring one puts it in the navigation and un-publishing takes
     * it out. Dev Roulette is absent by design: it is the dispatcher behind
     * "I'm Bored", not a library with a page worth browsing.
     */
    const experienceNavItems: NavItem[] = navExperiences.map((experience) => ({
        title: experience.name,
        href: experienceShow(experience.slug),
        icon: experienceIcon(experience.icon),
    }));

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            {/*
                             * Signed in, the logo goes where the work is.
                             * Signed out, it goes to the pitch — the sidebar
                             * renders for guests too, and sending them to a
                             * dashboard they cannot see is a redirect, not
                             * navigation.
                             */}
                            <Link
                                href={signedIn ? dashboard() : home()}
                                prefetch
                            >
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain label="Play" items={playNavItems} />
                <NavMain label="Experiences" items={experienceNavItems} />
                <NavMain
                    label="Progress"
                    items={
                        signedIn
                            ? [
                                  {
                                      title: 'Dashboard',
                                      href: dashboard(),
                                      icon: LayoutGrid,
                                  },
                                  ...progressNavItems,
                              ]
                            : progressNavItems
                    }
                />
            </SidebarContent>

            <SidebarFooter>
                {signedIn ? <NavUser /> : <GuestActions />}
            </SidebarFooter>
        </Sidebar>
    );
}

/**
 * What the footer offers someone who is not signed in.
 *
 * The catalogue, the achievements and the leaderboards are all public, so a
 * guest can get several pages deep into DevLab through this sidebar. Until now
 * the only thing waiting for them at the bottom of it was the empty space where
 * a signed-in user's menu goes.
 */
function GuestActions() {
    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton asChild tooltip={{ children: 'Log in' }}>
                    <Link href={login()}>
                        <LogIn />
                        <span>Log in</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
            <SidebarMenuItem>
                <SidebarMenuButton asChild tooltip={{ children: 'Sign up' }}>
                    <Link href={register()}>
                        <UserPlus />
                        <span>Sign up</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
