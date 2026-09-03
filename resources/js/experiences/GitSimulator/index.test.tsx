import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import GitSimulator, { type GitSimulatorConfiguration } from './index';

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
    overrides: Partial<GitSimulatorConfiguration> = {},
): GitSimulatorConfiguration {
    return {
        goal: 'Put feature on top of main with no merge commit.',
        repository: {
            commits: [
                { id: 'a1', message: 'Initial commit', parents: [] },
                { id: 'a2', message: 'Second on main', parents: ['a1'] },
                { id: 'b1', message: 'On feature', parents: ['a1'] },
            ],
            branches: { main: 'a2', feature: 'b1' },
            head: 'main',
        },
        ...overrides,
    };
}

function run(command: string) {
    fireEvent.change(screen.getByLabelText('Git command'), {
        target: { value: command },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Run' }));
}

function submitted(): string[] {
    return [
        ...document.querySelectorAll<HTMLInputElement>(
            'input[name="submission[commands][]"]',
        ),
    ].map((input) => input.value);
}

describe('GitSimulator', () => {
    it('states the goal and draws the starting history', () => {
        render(<GitSimulator configuration={configuration()} attemptId={1} />);

        expect(screen.getByText(/no merge commit/)).toBeTruthy();
        expect(screen.getByText('Initial commit')).toBeTruthy();
        expect(screen.getByText('Second on main')).toBeTruthy();
    });

    it('redraws the history as commands are run', () => {
        /*
         * The reason a Git model exists in the browser at all: a player has to
         * see the graph change, or this is a text adventure about a program they
         * cannot see.
         */
        render(<GitSimulator configuration={configuration()} attemptId={1} />);

        expect(screen.queryByText('Add a thing')).toBeNull();

        run('git commit -m "Add a thing"');

        expect(screen.getByText('Add a thing')).toBeTruthy();
    });

    it('submits the commands, never the resulting graph', () => {
        // Law 1. The server replays these; a claimed end state would be a
        // verdict from the browser.
        render(<GitSimulator configuration={configuration()} attemptId={1} />);

        run('git checkout feature');
        run('git rebase main');

        expect(submitted()).toEqual([
            'git checkout feature',
            'git rebase main',
        ]);
    });

    it('shows where HEAD is, and says so loudly when it is detached', () => {
        render(<GitSimulator configuration={configuration()} attemptId={1} />);

        expect(screen.getByText(/HEAD →/).textContent).toContain('main');

        run('git checkout b1');

        expect(screen.getByText(/detached at b1/)).toBeTruthy();
    });

    it('reports a command that will not apply, and refuses to submit', () => {
        render(<GitSimulator configuration={configuration()} attemptId={1} />);

        run('git checkout nowhere');

        expect(screen.getByRole('alert').textContent).toContain(
            "no branch or commit called 'nowhere'",
        );
        expect(
            (
                screen.getByRole('button', {
                    name: /submit these/i,
                }) as HTMLButtonElement
            ).disabled,
        ).toBe(true);
    });

    it('undoes the last command', () => {
        /*
         * Git's real undo is the reflog, which this model does not have. A
         * simulator you cannot back out of teaches fear rather than Git.
         */
        render(<GitSimulator configuration={configuration()} attemptId={1} />);

        run('git commit -m "One"');
        run('git commit -m "Two"');

        expect(screen.getByText('Two')).toBeTruthy();

        fireEvent.click(screen.getByRole('button', { name: 'undo' }));

        expect(screen.queryByText('Two')).toBeNull();
        expect(screen.getByText('One')).toBeTruthy();
        expect(submitted()).toEqual(['git commit -m "One"']);
    });

    it('runs on Enter rather than submitting the attempt', () => {
        // Submitting by accident here ends the attempt.
        render(<GitSimulator configuration={configuration()} attemptId={1} />);

        const input = screen.getByLabelText('Git command');

        fireEvent.change(input, { target: { value: 'git commit -m "Typed"' } });
        fireEvent.keyDown(input, { key: 'Enter' });

        expect(screen.getByText('Typed')).toBeTruthy();
    });

    it('will not submit nothing', () => {
        render(<GitSimulator configuration={configuration()} attemptId={1} />);

        expect(
            (
                screen.getByRole('button', {
                    name: /submit these/i,
                }) as HTMLButtonElement
            ).disabled,
        ).toBe(true);
    });

    it('names the commands a challenge restricts itself to', () => {
        render(
            <GitSimulator
                configuration={configuration({ allowed: ['revert'] })}
                attemptId={1}
            />,
        );

        expect(screen.getByText(/Allowed here: revert/)).toBeTruthy();

        run('git reset a1');

        expect(screen.getByRole('alert').textContent).toContain(
            "does not allow 'git reset'",
        );
    });

    it('replays a closed attempt with no way to change it', () => {
        render(
            <GitSimulator
                configuration={configuration()}
                attemptId={1}
                readOnly
                chosen={JSON.stringify([
                    'git checkout feature',
                    'git commit -m "Replayed"',
                ])}
            />,
        );

        expect(screen.getByText('Replayed')).toBeTruthy();
        expect(screen.queryByLabelText('Git command')).toBeNull();
        expect(screen.queryByRole('button', { name: 'undo' })).toBeNull();
    });

    it('renders a closed attempt whose submission cannot be read', () => {
        render(
            <GitSimulator
                configuration={configuration()}
                attemptId={1}
                readOnly
                chosen="not json"
            />,
        );

        expect(screen.getByText('Nothing run yet.')).toBeTruthy();
    });
});
