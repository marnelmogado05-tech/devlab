import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import AuthRailLayout from './auth-rail-layout';

vi.mock('@inertiajs/react', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();

    return {
        ...actual,
        Head: () => null,
        usePage: () => ({ props: { auth: { user: null } } }),
    };
});

describe('the auth shell', () => {
    it('wears the same wordmark as the rest of the application', () => {
        /*
         * The point of replacing the starter kit's layout. Its version put the
         * Laravel logo above the form, so the page you sign in on advertised a
         * different product than the one you were signing in to.
         */
        render(<AuthRailLayout title="Log in">form</AuthRailLayout>);

        // The slash is its own element, so the accessible name comes back with
        // whitespace around it.
        const wordmark = screen.getByRole('link', { name: /dev\s*\/\s*lab/i });

        expect(wordmark.getAttribute('href')).toBe('/');
    });

    it('announces the page title as the heading', () => {
        render(<AuthRailLayout title="Log in">form</AuthRailLayout>);

        expect(
            screen.getByRole('heading', { level: 1, name: 'Log in' }),
        ).toBeTruthy();
    });

    it('carries a theme control, because no other surface here has one', () => {
        /*
         * The settings page that used to hold the appearance switcher is gone,
         * and a signed-out visitor cannot reach settings anyway. If the auth
         * shell does not offer it, someone who lands on `/login` in the wrong
         * theme has no way out of it.
         */
        render(<AuthRailLayout title="Log in">form</AuthRailLayout>);

        expect(screen.getByRole('group', { name: /theme/i })).toBeTruthy();
    });

    it('leaves the accent button off the page', () => {
        /*
         * `BoredButton` is the loudest control in the system and the only thing
         * wearing yellow. Beside a "Log in" submit it would win, and winning a
         * login page is the wrong job for it.
         */
        render(<AuthRailLayout title="Log in">form</AuthRailLayout>);

        expect(screen.queryByRole('link', { name: /bored/i })).toBeNull();
    });

    it('pitches an account to a guest', () => {
        render(<AuthRailLayout title="Log in">form</AuthRailLayout>);

        expect(screen.getByText(/keep the score/i)).toBeTruthy();
    });

    it('does not pitch an account to someone already signed in', () => {
        /*
         * `secure` is confirm-password, the two-factor challenge and email
         * verification — everyone on those pages already has an account, so
         * selling them one reads as the layout not knowing who it is talking
         * to.
         */
        render(
            <AuthRailLayout title="Confirm your password" variant="secure">
                form
            </AuthRailLayout>,
        );

        expect(screen.queryByText(/keep the score/i)).toBeNull();
        expect(screen.getByText(/checkpoint, not a wall/i)).toBeTruthy();
    });
});
