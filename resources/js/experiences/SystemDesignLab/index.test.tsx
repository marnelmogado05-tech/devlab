import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import SystemDesignLab, { type SystemDesignLabConfiguration } from './index';

vi.mock('@inertiajs/react', async (importOriginal) => {
    const actual = await importOriginal<Record<string, unknown>>();

    return {
        ...actual,
        Form: ({ children, ...props }: Record<string, unknown>) => (
            <form data-testid="inertia-form" {...props}>
                {typeof children === 'function'
                    ? (children as (state: unknown) => React.ReactNode)({
                          processing: false,
                          errors: {},
                      })
                    : (children as React.ReactNode)}
            </form>
        ),
    };
});

function configuration(
    overrides: Partial<SystemDesignLabConfiguration> = {},
): SystemDesignLabConfiguration {
    return {
        scenario: 'A URL shortener, almost all reads.',
        requirements: [
            {
                key: 'read_throughput',
                text: 'Serve a million redirects per second',
            },
            { key: 'durability', text: 'Never lose a link' },
        ],
        slots: [
            {
                key: 'cache',
                label: 'Read path',
                hint: 'Costs memory; buys read throughput.',
                options: [
                    { key: 'none', text: 'No cache' },
                    { key: 'read_through', text: 'Read-through cache' },
                ],
            },
            {
                key: 'storage',
                label: 'Storage',
                options: [
                    { key: 'single', text: 'One instance' },
                    { key: 'replicated', text: 'Primary with replicas' },
                ],
            },
        ],
        ...overrides,
    };
}

describe('SystemDesignLab', () => {
    it('states the brief and every requirement', () => {
        render(
            <SystemDesignLab configuration={configuration()} attemptId={1} />,
        );

        expect(screen.getByText(/almost all reads/)).toBeTruthy();
        expect(screen.getByText(/million redirects/)).toBeTruthy();
        expect(screen.getByText(/Never lose a link/)).toBeTruthy();
    });

    it('offers one radio group per slot, named for the server', () => {
        render(
            <SystemDesignLab configuration={configuration()} attemptId={1} />,
        );

        const cache = screen.getByLabelText(
            'Read-through cache',
        ) as HTMLInputElement;

        expect(cache.name).toBe('submission[choices][cache]');
        expect(cache.value).toBe('read_through');
    });

    it('shows a hint as a cost, and only where the author wrote one', () => {
        render(
            <SystemDesignLab configuration={configuration()} attemptId={1} />,
        );

        expect(screen.getByText(/Costs memory/)).toBeTruthy();
    });

    it('will not submit an incomplete design', () => {
        /*
         * A partial design gets scored against requirements it never tried to
         * meet, which reads as the rubric being unfair rather than the
         * submission being unfinished. The server rejects it too; this just
         * stops the player finding that out the hard way.
         */
        render(
            <SystemDesignLab configuration={configuration()} attemptId={1} />,
        );

        const button = screen.getByRole('button', {
            name: /submit design/i,
        }) as HTMLButtonElement;

        expect(button.disabled).toBe(true);

        fireEvent.click(screen.getByLabelText('Read-through cache'));
        expect(button.disabled).toBe(true);

        fireEvent.click(screen.getByLabelText('Primary with replicas'));
        expect(button.disabled).toBe(false);
    });

    it('counts decisions made without judging them', () => {
        /*
         * The count is something the client legitimately knows. Whether the
         * design is any good is not: computing that needs the rubric, and
         * shipping the rubric would hand over the answer key.
         */
        render(
            <SystemDesignLab configuration={configuration()} attemptId={1} />,
        );

        expect(screen.getByText('0 of 2 decided')).toBeTruthy();

        fireEvent.click(screen.getByLabelText('No cache'));

        expect(screen.getByText('1 of 2 decided')).toBeTruthy();
    });

    it('replaces a choice within a slot rather than adding to it', () => {
        render(
            <SystemDesignLab configuration={configuration()} attemptId={1} />,
        );

        fireEvent.click(screen.getByLabelText('No cache'));
        fireEvent.click(screen.getByLabelText('Read-through cache'));

        expect(
            (screen.getByLabelText('No cache') as HTMLInputElement).checked,
        ).toBe(false);
        expect(screen.getByText('1 of 2 decided')).toBeTruthy();
    });

    it('rebuilds the submitted design on a closed attempt, with no way to change it', () => {
        render(
            <SystemDesignLab
                configuration={configuration()}
                attemptId={1}
                readOnly
                chosen={JSON.stringify({
                    cache: 'read_through',
                    storage: 'single',
                })}
            />,
        );

        expect(
            (screen.getByLabelText('Read-through cache') as HTMLInputElement)
                .checked,
        ).toBe(true);
        expect(
            (screen.getByLabelText('One instance') as HTMLInputElement).checked,
        ).toBe(true);
        expect(screen.queryByRole('button')).toBeNull();
    });

    it('renders a closed attempt whose submission cannot be read', () => {
        // A challenge can change under a finished attempt. An unreadable answer
        // means an empty board, never a crash.
        render(
            <SystemDesignLab
                configuration={configuration()}
                attemptId={1}
                readOnly
                chosen="not json"
            />,
        );

        expect(
            (screen.getByLabelText('No cache') as HTMLInputElement).checked,
        ).toBe(false);
    });
});
