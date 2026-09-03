import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { AppSidebar } from './app-sidebar';
import { SidebarProvider } from '@/components/ui/sidebar';
import { TooltipProvider } from '@/components/ui/tooltip';

const page = {
    url: '/experiences',
    props: {
        auth: { user: null as { name: string } | null },
        navExperiences: [
            { slug: 'cursed-code', name: 'Cursed Code', icon: 'Ghost' },
            { slug: 'bug-hunter', name: 'Bug Hunter', icon: 'Bug' },
        ],
    },
};

vi.mock('@inertiajs/react', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();

    return {
        ...actual,
        usePage: () => page,
    };
});

/*
 * The sidebar reads its open state from context and its collapsed-mode labels
 * from tooltips, so it needs both providers — the same two `app.tsx` wraps the
 * real application in.
 */
function renderSidebar() {
    return render(
        <TooltipProvider>
            <SidebarProvider>
                <AppSidebar />
            </SidebarProvider>
        </TooltipProvider>,
    );
}

function href(name: RegExp) {
    return screen.getByRole('link', { name }).getAttribute('href');
}

describe('AppSidebar', () => {
    beforeEach(() => {
        page.props.auth.user = null;
    });

    it('leads with the button the product is named for', () => {
        renderSidebar();

        expect(href(/bored/i)).toBe('/bored');
    });

    it('lists the experiences the server published, not a hardcoded set', () => {
        /*
         * The regression this exists to stop: someone authors an experience,
         * publishes it, and it never appears in the navigation because the list
         * lives in the frontend. The names here come from props for that reason.
         */
        renderSidebar();

        expect(href(/cursed code/i)).toBe('/experiences/cursed-code');
        expect(href(/bug hunter/i)).toBe('/experiences/bug-hunter');
    });

    it('shows no experiences when the catalogue is empty, rather than an empty heading', () => {
        page.props.navExperiences = [];

        renderSidebar();

        expect(screen.queryByText('Experiences')).toBeNull();

        page.props.navExperiences = [
            { slug: 'cursed-code', name: 'Cursed Code', icon: 'Ghost' },
            { slug: 'bug-hunter', name: 'Bug Hunter', icon: 'Bug' },
        ];
    });

    it('offers a guest a way in, and no dashboard they cannot reach', () => {
        renderSidebar();

        expect(screen.getByRole('link', { name: /log in/i })).toBeTruthy();
        expect(screen.getByRole('link', { name: /sign up/i })).toBeTruthy();
        expect(screen.queryByRole('link', { name: /dashboard/i })).toBeNull();
    });

    it('swaps those for the dashboard once someone is signed in', () => {
        page.props.auth.user = { name: 'Ada Lovelace' };

        renderSidebar();

        expect(href(/dashboard/i)).toBe('/dashboard');
        expect(screen.queryByRole('link', { name: /sign up/i })).toBeNull();
    });

    it('keeps the public pages public', () => {
        // Achievements and leaderboards are reachable before signing up: a
        // visitor can see what there is to earn before deciding to.
        renderSidebar();

        expect(href(/achievements/i)).toBe('/achievements');
        expect(href(/leaderboards/i)).toBe('/leaderboards');
    });

    it('points its footer at DevLab, not at the starter kit it grew out of', () => {
        renderSidebar();

        expect(href(/repository/i)).toContain('devlab');
        expect(href(/documentation/i)).not.toContain('laravel.com');
    });
});
