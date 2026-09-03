import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import Welcome from './welcome';

/*
 * `Head` needs a running Inertia app to write into the document head, and
 * `usePage` needs its context. Neither is what this file is about: the page's
 * job is to put the button in front of a stranger, and that is asserted on the
 * rendered output.
 */
vi.mock('@inertiajs/react', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();

    return {
        ...actual,
        Head: () => null,
        usePage: () => ({ props: { auth: { user: null } } }),
    };
});

const experiences = [
    {
        slug: 'cursed-code',
        name: 'Cursed Code',
        tagline: 'Code that works, and should not.',
        challenges_count: 6,
    },
];

describe('the landing page', () => {
    it('offers the button the product is named for', () => {
        /*
         * The one assertion this page cannot afford to lose. DevLab's pitch is
         * "open it, press the button, get handed something" — a landing page
         * without the button is the product failing at the front door.
         */
        render(<Welcome experiences={experiences} challengeCount={12} />);

        const button = screen.getByRole('link', { name: /bored/i });

        expect(button.getAttribute('href')).toBe('/bored');
    });

    it('states the real challenge count it was given', () => {
        // The count is read at request time precisely so it cannot drift from
        // reality. Hard-coding it back into the copy would undo that.
        render(<Welcome experiences={experiences} challengeCount={12} />);

        expect(screen.getAllByText(/12/).length).toBeGreaterThan(0);
    });

    it('offers a stranger a way in, not a way to sign out', () => {
        render(<Welcome experiences={experiences} challengeCount={12} />);

        expect(screen.getByRole('link', { name: /log in/i })).toBeTruthy();
        expect(screen.getByRole('link', { name: /sign up/i })).toBeTruthy();
        expect(screen.queryByRole('link', { name: /dashboard/i })).toBeNull();
    });
});
