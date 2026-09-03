import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import Dashboard from './dashboard';

vi.mock('@inertiajs/react', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();

    return {
        ...actual,
        Head: () => null,
    };
});

type Props = Parameters<typeof Dashboard>[0];

function props(overrides: Partial<Props> = {}): Props {
    return {
        progression: {
            total_xp: 250,
            level: { level: 2, title: 'Script Kiddie', xp_required: 100 },
            next_level: { level: 3, title: 'Junior', xp_required: 500 },
            progress: 0.375,
            rank: 4,
        },
        statistics: {
            challenges_completed: 3,
            success_rate: 0.75,
            current_streak_days: 2,
            achievements_unlocked: 1,
        },
        openAttempts: [],
        recent: [],
        achievements: [],
        username: 'ada',
        ...overrides,
    };
}

const openAttempt = {
    id: 7,
    challenge: {
        slug: 'floating-point',
        title: 'Floating point',
        difficulty: 'medium',
    },
    experience: 'Cursed Code',
    started_at: new Date().toISOString(),
    expires_at: new Date(Date.now() + 90 * 60_000).toISOString(),
};

describe('the dashboard', () => {
    it('always offers the button', () => {
        render(<Dashboard {...props()} />);

        expect(
            screen.getByRole('link', { name: /bored/i }).getAttribute('href'),
        ).toBe('/bored');
    });

    it('puts unfinished work above everything, with a way back into it', () => {
        /*
         * The one thing this page must not bury. Handing someone a new
         * challenge while an unfinished one counts down towards being expired
         * is the only way a dashboard can actively waste their time.
         */
        render(<Dashboard {...props({ openAttempts: [openAttempt] })} />);

        const resume = screen.getByRole('link', { name: /resume/i });

        expect(resume.getAttribute('href')).toBe('/attempts/7');
        expect(screen.getByText('Floating point')).toBeTruthy();
    });

    it('says how long is left, and keeps the exact instant in the markup', () => {
        // The rounded text is for reading; `dateTime` is the record of when the
        // scheduler will actually take it.
        render(<Dashboard {...props({ openAttempts: [openAttempt] })} />);

        const time = screen.getByText(/left$/);

        expect(time.getAttribute('datetime')).toBe(openAttempt.expires_at);
        expect(time.textContent).toBe('1h left');
    });

    it('shows no "still open" section when there is nothing open', () => {
        render(<Dashboard {...props()} />);

        expect(screen.queryByText(/still open/i)).toBeNull();
    });

    it('distinguishes no data from a bad record', () => {
        // A null success rate means nothing has been finished. Rendering it as
        // 0% would accuse a new player of never being right.
        render(
            <Dashboard
                {...props({
                    statistics: {
                        challenges_completed: 0,
                        success_rate: null,
                        current_streak_days: 0,
                        achievements_unlocked: 0,
                    },
                })}
            />,
        );

        expect(screen.getByText('—')).toBeTruthy();
    });

    it('names the outcome of a finished attempt in words, not only colour', () => {
        // §44: colour is never the only carrier of state.
        render(
            <Dashboard
                {...props({
                    recent: [
                        {
                            challenge: {
                                slug: 'floating-point',
                                title: 'Floating point',
                                difficulty: 'medium',
                            },
                            experience: 'Cursed Code',
                            solved: false,
                            completed_at: new Date().toISOString(),
                        },
                    ],
                })}
            />,
        );

        expect(screen.getByText('failed')).toBeTruthy();
    });

    it('greets a new player differently from a returning one', () => {
        render(
            <Dashboard
                {...props({
                    statistics: {
                        challenges_completed: 0,
                        success_rate: null,
                        current_streak_days: 0,
                        achievements_unlocked: 0,
                    },
                })}
            />,
        );

        expect(screen.getByText(/welcome to devlab/i)).toBeTruthy();
    });

    it('links to the public profile when there is a username to link to', () => {
        render(<Dashboard {...props()} />);

        expect(
            screen
                .getByRole('link', { name: /public profile/i })
                .getAttribute('href'),
        ).toBe('/profile/ada');
    });
});
