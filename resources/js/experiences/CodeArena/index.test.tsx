import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import CodeArena, { type CodeArenaConfiguration } from './index';

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
    overrides: Partial<CodeArenaConfiguration> = {},
): CodeArenaConfiguration {
    return {
        runtime: 'php-8.4',
        entry: 'solve',
        signature: 'function solve(array $n): int',
        brief: 'Sum the list.',
        starter: '<?php\n\nfunction solve(array $n): int {}\n',
        cases: [
            { args: [[1, 2, 3]], sample: true, expected: 6 },
            // A hidden case arrives with its inputs and WITHOUT its answer. The
            // server withholds it; this is what the client actually receives.
            { args: [[]], sample: false },
        ],
        ...overrides,
    };
}

const finishedRun = {
    id: 7,
    status: 'finished',
    failure_reason: null,
    killed_by: null,
    truncated: false,
    duration_ms: 42,
    exit_code: 0,
    created_at: '2026-09-04T10:00:00+00:00',
    source: '<?php',
    stderr: null,
    results: {
        passed: 1,
        total: 2,
        cases: [
            {
                case: 0,
                label: null,
                sample: true,
                status: 'ok',
                passed: true,
                args: [[1, 2, 3]],
                expected: 6,
                returned: 6,
                has_value: true,
                output: null,
                duration_ms: 2,
            },
            {
                case: 1,
                label: null,
                sample: false,
                status: 'ok',
                passed: false,
                args: [[]],
                expected: null,
                returned: 99,
                has_value: true,
                output: null,
                duration_ms: 2,
            },
        ],
    },
};

function mockRuns(runs: unknown[]) {
    return vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({ runs, remaining: 24 }),
    });
}

beforeEach(() => {
    vi.stubGlobal('fetch', mockRuns([]));
});

afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

describe('CodeArena', () => {
    it('starts from the challenge starter code', () => {
        render(<CodeArena configuration={configuration()} attemptId={1} />);

        expect(
            (screen.getByLabelText('Your solution') as HTMLTextAreaElement)
                .value,
        ).toBe(configuration().starter);
    });

    it('shows the worked examples with their answers', () => {
        render(<CodeArena configuration={configuration()} attemptId={1} />);

        expect(screen.getByText('[[1,2,3]]')).toBeTruthy();
        expect(screen.getByText('6')).toBeTruthy();
    });

    it('cannot submit before a run has finished', () => {
        /*
         * The submission IS a run id, so there is nothing to submit until one
         * exists. The server refuses too — this only spares the player a
         * pointless round trip.
         */
        render(<CodeArena configuration={configuration()} attemptId={1} />);

        expect(
            (
                screen.getByRole('button', {
                    name: 'Submit this run',
                }) as HTMLButtonElement
            ).disabled,
        ).toBe(true);
    });

    it('reports a finished run case by case', async () => {
        vi.stubGlobal('fetch', mockRuns([finishedRun]));

        render(<CodeArena configuration={configuration()} attemptId={1} />);

        expect(await screen.findByText('1 of 2 cases passed')).toBeTruthy();
        expect(screen.getByText('PASS')).toBeTruthy();
        expect(screen.getByText('FAIL')).toBeTruthy();
    });

    it('marks a hidden case as hidden and shows no expectation for it', async () => {
        vi.stubGlobal('fetch', mockRuns([finishedRun]));

        render(<CodeArena configuration={configuration()} attemptId={1} />);

        await screen.findByText('1 of 2 cases passed');

        // Scoped to the hidden case's own row — the samples list above it has
        // `li`s of its own, and indexing across both asserted nothing.
        const hidden = screen.getByText(/\(hidden\)/).closest('li');

        expect(hidden).not.toBeNull();
        // "want" is the expectation label, and it is rendered for samples only.
        expect(hidden?.textContent).not.toContain('want');
        expect(hidden?.textContent).toContain('got 99');
    });

    it("says a declined run was not the player's fault", async () => {
        /*
         * S7 reaching the person it affects. A run the platform could not
         * complete must not read as a failed answer — the attempt is untouched
         * and they can simply try again.
         */
        vi.stubGlobal(
            'fetch',
            mockRuns([
                {
                    ...finishedRun,
                    status: 'unavailable',
                    failure_reason: 'unavailable',
                    results: null,
                },
            ]),
        );

        render(<CodeArena configuration={configuration()} attemptId={1} />);

        expect(
            await screen.findByText('This run did not happen.'),
        ).toBeTruthy();
        expect(screen.getByText(/Your attempt is untouched/)).toBeTruthy();
    });

    it('posts the edited source when the player runs it', async () => {
        const fetchMock = vi
            .fn()
            .mockImplementation((_url: string, init?: RequestInit) =>
                Promise.resolve(
                    init?.method === 'POST'
                        ? {
                              ok: true,
                              json: async () => ({
                                  run: {
                                      ...finishedRun,
                                      results: null,
                                      status: 'queued',
                                  },
                              }),
                          }
                        : {
                              ok: true,
                              json: async () => ({ runs: [], remaining: 24 }),
                          },
                ),
            );

        vi.stubGlobal('fetch', fetchMock);

        render(<CodeArena configuration={configuration()} attemptId={1} />);

        const editor = screen.getByLabelText('Your solution');

        await userEvent.clear(editor);
        await userEvent.type(editor, 'mine');
        await userEvent.click(screen.getByRole('button', { name: 'Run' }));

        await waitFor(() => {
            const post = fetchMock.mock.calls.find(
                ([, init]) =>
                    (init as RequestInit | undefined)?.method === 'POST',
            );

            // Narrowed rather than optional-chained: `post?.[1]` short-circuits
            // to undefined when no POST was made, and the cast then hides that
            // behind a TypeError instead of the assertion that was meant to fail.
            if (!post) {
                throw new Error('No POST was made.');
            }

            expect(JSON.parse((post[1] as RequestInit).body as string)).toEqual(
                {
                    source: 'mine',
                },
            );
        });
    });

    it('does not offer a Run button on a closed attempt', () => {
        render(
            <CodeArena
                configuration={configuration()}
                attemptId={1}
                readOnly
            />,
        );

        expect(screen.queryByRole('button', { name: 'Run' })).toBeNull();
        expect(
            screen.queryByRole('button', { name: 'Submit this run' }),
        ).toBeNull();
    });
});
