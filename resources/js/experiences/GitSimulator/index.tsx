import { Form } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { submit } from '@/routes/attempts';
import { replay, type RepositoryState } from './git';

export interface GitSimulatorConfiguration {
    goal: string;
    repository: RepositoryState;
    allowed?: string[] | null;
}

const MAX_COMMANDS = 30;

/**
 * The Git Simulator interface: type commands, watch the history change.
 *
 * The graph is drawn by a model in the browser (see `git.ts`) so the player can
 * see what they are doing. It decides nothing — the command sequence is what
 * gets submitted, and the server replays it against its own model to reach a
 * verdict (law 1, ADR 0006). Nothing here is ever told the goal state.
 *
 * Commands are kept as a list rather than a single text box so a player can undo
 * the last one. Git's real undo is the reflog, which this model does not have,
 * and a simulator you cannot back out of teaches fear rather than Git.
 */
export default function GitSimulator({
    configuration,
    attemptId,
    readOnly = false,
    chosen = null,
}: {
    configuration: GitSimulatorConfiguration;
    attemptId: number;
    readOnly?: boolean;
    chosen?: string | null;
}) {
    const [commands, setCommands] = useState<string[]>(() =>
        parseChosen(chosen),
    );
    const [draft, setDraft] = useState('');

    const allowed = configuration.allowed ?? undefined;

    // Replayed from the start every time rather than applied incrementally:
    // undo then becomes dropping a command rather than inverting one, and there
    // is no inverse of `git reset` to write.
    const state = useMemo(
        () => replay(configuration.repository, commands, allowed),
        [configuration.repository, commands, allowed],
    );

    function run(): void {
        const command = draft.trim();

        if (command === '' || commands.length >= MAX_COMMANDS) {
            return;
        }

        setCommands((current) => [...current, command]);
        setDraft('');
    }

    const body = (disabled: boolean, processing: boolean) => (
        <fieldset disabled={disabled} className="space-y-6">
            <legend className="sr-only">Fix the repository</legend>

            <section className="space-y-2">
                <h3 className="font-mono text-xs tracking-widest uppercase">
                    Goal
                </h3>
                <p className="text-sm leading-relaxed">{configuration.goal}</p>
            </section>

            <Graph repository={state.repository} />

            <section className="space-y-2">
                <h3 className="font-mono text-xs tracking-widest uppercase">
                    Commands
                </h3>

                {commands.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        Nothing run yet.
                    </p>
                ) : (
                    <ol className="divide-border overflow-hidden rounded-md border font-mono text-xs">
                        {commands.map((command, index) => (
                            <li
                                key={`${index}-${command}`}
                                className={cn(
                                    'flex items-center justify-between gap-3 border-b px-3 py-1.5 last:border-0',
                                    state.error !== null &&
                                        index === state.applied &&
                                        'bg-destructive/10',
                                )}
                            >
                                <span className="truncate">{command}</span>
                                {!disabled && index === commands.length - 1 && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setCommands((current) =>
                                                current.slice(0, -1),
                                            )
                                        }
                                        className="text-muted-foreground hover:text-foreground shrink-0"
                                    >
                                        undo
                                    </button>
                                )}
                            </li>
                        ))}
                    </ol>
                )}

                {state.error !== null && (
                    <p role="alert" className="text-destructive text-xs">
                        {state.error}
                    </p>
                )}

                {commands.map((command, index) => (
                    <input
                        key={`field-${index}`}
                        type="hidden"
                        name="submission[commands][]"
                        value={command}
                    />
                ))}
            </section>

            {!disabled && (
                <div className="space-y-3">
                    <div className="flex flex-wrap gap-2">
                        <Input
                            value={draft}
                            onChange={(event) => setDraft(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    // Enter runs the command rather than
                                    // submitting the attempt: submitting by
                                    // accident here ends the attempt.
                                    event.preventDefault();
                                    run();
                                }
                            }}
                            placeholder="git merge feature"
                            aria-label="Git command"
                            className="max-w-md font-mono"
                            autoComplete="off"
                            spellCheck={false}
                        />
                        <Button
                            type="button"
                            variant="outline"
                            onClick={run}
                            disabled={
                                draft.trim() === '' ||
                                commands.length >= MAX_COMMANDS
                            }
                        >
                            Run
                        </Button>
                    </div>

                    {allowed && (
                        <p className="text-muted-foreground font-mono text-xs">
                            Allowed here: {allowed.join(', ')}
                        </p>
                    )}

                    <Button
                        type="submit"
                        disabled={
                            processing ||
                            commands.length === 0 ||
                            state.error !== null
                        }
                    >
                        Submit these {commands.length || ''} commands
                    </Button>
                </div>
            )}
        </fieldset>
    );

    if (readOnly) {
        return body(true, false);
    }

    return (
        <Form action={submit(attemptId)} method="post" className="space-y-4">
            {({ processing }) => body(false, processing)}
        </Form>
    );
}

/**
 * The history, newest first, with branch labels and HEAD.
 *
 * A list rather than a drawn graph: the shape that matters in these challenges
 * is which commits are reachable from which pointer, and a text layout says that
 * exactly while staying readable to a screen reader. Lines and curves would look
 * more like Git and communicate less.
 */
function Graph({
    repository,
}: {
    repository: ReturnType<typeof replay>['repository'];
}) {
    const head = repository.head();
    const commits = [...repository.allCommits()].reverse();

    return (
        <section className="space-y-2">
            <h3 className="font-mono text-xs tracking-widest uppercase">
                History
            </h3>

            <div className="text-muted-foreground font-mono text-xs">
                HEAD →{' '}
                {repository.isDetached() ? (
                    <span className="text-destructive">
                        detached at {head ?? 'nothing'}
                    </span>
                ) : (
                    repository.currentBranch()
                )}
            </div>

            <ul className="divide-border overflow-hidden rounded-md border">
                {commits.map((commit) => {
                    const labels = repository.labelsAt(commit.id);

                    return (
                        <li
                            key={commit.id}
                            className={cn(
                                'flex flex-wrap items-center gap-2 border-b px-3 py-1.5 font-mono text-xs last:border-0',
                                commit.id === head && 'bg-primary/5',
                            )}
                        >
                            <span className="text-muted-foreground w-16 shrink-0">
                                {commit.id}
                            </span>
                            <span className="flex-1">{commit.message}</span>

                            {commit.parents.length > 1 && (
                                <span className="text-muted-foreground">
                                    merge
                                </span>
                            )}

                            {labels.map((label) => (
                                <span
                                    key={label}
                                    className="bg-muted rounded px-1.5 py-0.5"
                                >
                                    {label}
                                </span>
                            ))}

                            {commit.id === head && (
                                <span className="bg-primary text-primary-foreground rounded px-1.5 py-0.5">
                                    HEAD
                                </span>
                            )}
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}

/**
 * Rebuild a submitted command list for a closed attempt.
 *
 * Anything unreadable means an empty list rather than a crash: a closed attempt
 * must always render, including one made against a challenge that has since
 * changed.
 */
function parseChosen(chosen: string | null): string[] {
    if (!chosen) {
        return [];
    }

    try {
        const parsed: unknown = JSON.parse(chosen);

        if (!Array.isArray(parsed)) {
            return [];
        }

        return parsed.filter(
            (entry): entry is string => typeof entry === 'string',
        );
    } catch {
        return [];
    }
}
