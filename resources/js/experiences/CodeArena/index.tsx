import { Form } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { submit } from '@/routes/attempts';
import { index as runsIndex, store as runsStore } from '@/routes/attempts/runs';

export interface CodeArenaCase {
    args: unknown[];
    sample: boolean;
    label?: string | null;
    // Present on samples only. A hidden case ships its inputs and never its
    // answer — the server withholds it, this type just reflects that.
    expected?: unknown;
}

export interface CodeArenaConfiguration {
    runtime: string;
    entry: string;
    signature: string;
    brief: string;
    starter: string;
    cases: CodeArenaCase[];
}

interface CaseResult {
    case: number;
    label: string | null;
    sample: boolean;
    status: string;
    passed: boolean;
    args: unknown[];
    expected: unknown;
    returned: unknown;
    has_value: boolean;
    output: string | null;
    duration_ms: number | null;
}

interface Run {
    id: number;
    status: string;
    failure_reason: string | null;
    killed_by: string | null;
    truncated: boolean;
    duration_ms: number | null;
    exit_code: number | null;
    created_at: string;
    source: string;
    stderr: string | null;
    results: { cases: CaseResult[]; passed: number; total: number } | null;
}

const PENDING = ['queued', 'running'];

/**
 * The Code Arena interface: write a function, run it, submit a run.
 *
 * Presentation only, and unusually literally so. It shows what a sandbox already
 * did and what the server already decided about it; it never grades anything,
 * because it could not — the expectations for hidden cases are not sent here,
 * and were never sent to the sandbox either (ADR 0008).
 *
 * A plain textarea rather than a code editor component. Syntax highlighting is
 * worth a dependency when the phase that needs it arrives (law 7); it is not
 * worth one to ship the first experience that executes anything.
 */
export default function CodeArena({
    configuration,
    attemptId,
    readOnly = false,
    chosen = null,
}: {
    configuration: CodeArenaConfiguration;
    attemptId: number;
    readOnly?: boolean;
    chosen?: string | null;
}) {
    const [source, setSource] = useState(configuration.starter);
    const [runs, setRuns] = useState<Run[]>([]);
    const [selected, setSelected] = useState<number | null>(null);
    const [starting, setStarting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // The run the player submitted, on a closed attempt.
    const submittedRunId = chosen === null ? null : Number(chosen);

    const load = useCallback(async () => {
        const response = await fetch(runsIndex(attemptId).url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const body = (await response.json()) as { runs: Run[] };

        setRuns(body.runs);
        setSelected((current) => current ?? body.runs[0]?.id ?? null);
    }, [attemptId]);

    useEffect(() => {
        void load();
    }, [load]);

    /*
     * Polling exists because a run is asynchronous, and it stops as soon as
     * nothing is pending — a tab left open on a finished attempt must not keep
     * asking. The interval is cleared on unmount, so navigating away stops it
     * even mid-run.
     */
    const pending = runs.some((run) => PENDING.includes(run.status));
    const loadRef = useRef(load);
    loadRef.current = load;

    useEffect(() => {
        if (!pending) {
            return;
        }

        const timer = window.setInterval(() => void loadRef.current(), 1500);

        return () => window.clearInterval(timer);
    }, [pending]);

    const start = async () => {
        setStarting(true);
        setError(null);

        try {
            const response = await fetch(runsStore(attemptId).url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ source }),
            });

            if (!response.ok) {
                const body = (await response.json().catch(() => null)) as {
                    message?: string;
                } | null;

                setError(body?.message ?? 'That run could not be started.');

                return;
            }

            const body = (await response.json()) as { run: Run };

            setRuns((current) => [body.run, ...current]);
            setSelected(body.run.id);
        } finally {
            setStarting(false);
        }
    };

    const current =
        runs.find((run) => run.id === (submittedRunId ?? selected)) ?? null;
    const submittable = current !== null && current.status === 'finished';

    return (
        <div className="space-y-6">
            <section className="space-y-3">
                <p className="text-sm whitespace-pre-line">
                    {configuration.brief}
                </p>

                <p className="bg-muted rounded-md px-3 py-2 font-mono text-xs">
                    {configuration.signature}
                </p>

                <Samples cases={configuration.cases} />
            </section>

            <section className="space-y-2">
                <label
                    htmlFor={`source-${attemptId}`}
                    className="text-sm font-medium"
                >
                    Your solution
                </label>

                <textarea
                    id={`source-${attemptId}`}
                    value={readOnly ? (current?.source ?? source) : source}
                    onChange={(event) => setSource(event.target.value)}
                    readOnly={readOnly}
                    spellCheck={false}
                    rows={16}
                    className={cn(
                        'border-input bg-background w-full rounded-md border p-3',
                        'font-mono text-sm whitespace-pre',
                        'focus-visible:ring-ring focus-visible:ring-1 focus-visible:outline-none',
                    )}
                />

                {!readOnly && (
                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => void start()}
                            disabled={starting}
                        >
                            {starting ? 'Starting…' : 'Run'}
                        </Button>

                        <span className="text-muted-foreground text-xs">
                            Runs in a sandbox with no network. Run it, then
                            submit the run you want graded.
                        </span>
                    </div>
                )}

                {error && (
                    <p role="alert" className="text-destructive text-sm">
                        {error}
                    </p>
                )}
            </section>

            {runs.length > 0 && (
                <RunHistory
                    runs={runs}
                    selected={submittedRunId ?? selected}
                    onSelect={readOnly ? undefined : setSelected}
                />
            )}

            {current && <RunDetail run={current} />}

            {!readOnly && (
                <Form action={submit(attemptId)} method="post">
                    {({ processing, errors }) => (
                        <div className="space-y-2">
                            <input
                                type="hidden"
                                name="submission[run_id]"
                                value={current?.id ?? ''}
                            />

                            {errors['submission.run_id'] && (
                                <p
                                    role="alert"
                                    className="text-destructive text-sm"
                                >
                                    Pick a finished run to submit.
                                </p>
                            )}

                            <Button
                                type="submit"
                                disabled={processing || !submittable}
                            >
                                {processing ? 'Submitting…' : 'Submit this run'}
                            </Button>

                            {!submittable && (
                                <p className="text-muted-foreground text-xs">
                                    Run your code first. Only a finished run can
                                    be submitted — a run the platform could not
                                    complete is never counted against you.
                                </p>
                            )}
                        </div>
                    )}
                </Form>
            )}
        </div>
    );
}

/** The worked examples: the only cases whose answers are public. */
function Samples({ cases }: { cases: CodeArenaCase[] }) {
    const samples = cases
        .map((value, index) => ({ ...value, index }))
        .filter((value) => value.sample);

    if (samples.length === 0) {
        return null;
    }

    return (
        <ul className="divide-border overflow-hidden rounded-md border font-mono text-xs">
            {samples.map((sample) => (
                <li
                    key={sample.index}
                    className="flex flex-wrap gap-2 px-3 py-2"
                >
                    <span className="text-muted-foreground">in</span>
                    <code>{JSON.stringify(sample.args)}</code>
                    <span className="text-muted-foreground">out</span>
                    <code>{JSON.stringify(sample.expected)}</code>
                </li>
            ))}
        </ul>
    );
}

function RunHistory({
    runs,
    selected,
    onSelect,
}: {
    runs: Run[];
    selected: number | null;
    onSelect?: (id: number) => void;
}) {
    return (
        <div className="flex flex-wrap gap-2">
            {runs.map((run) => (
                <button
                    key={run.id}
                    type="button"
                    onClick={onSelect ? () => onSelect(run.id) : undefined}
                    disabled={!onSelect}
                    className={cn(
                        'rounded-md border px-2 py-1 font-mono text-xs',
                        run.id === selected && 'ring-ring ring-1',
                        onSelect && 'hover:bg-muted',
                    )}
                >
                    #{run.id}{' '}
                    {run.results
                        ? `${run.results.passed}/${run.results.total}`
                        : statusLabel(run.status)}
                </button>
            ))}
        </div>
    );
}

function RunDetail({ run }: { run: Run }) {
    if (run.status === 'unavailable' || run.status === 'stale') {
        return (
            <div role="status" className="rounded-md border p-3 text-sm">
                <p className="font-medium">This run did not happen.</p>
                <p className="text-muted-foreground">
                    {run.failure_reason === 'quota'
                        ? 'You already had runs in flight. Wait for one to finish and try again.'
                        : 'The platform could not run it. Your attempt is untouched — try again.'}
                </p>
            </div>
        );
    }

    if (run.results === null) {
        return (
            <p role="status" className="text-muted-foreground text-sm">
                Run #{run.id} is {statusLabel(run.status)}…
            </p>
        );
    }

    return (
        <div className="space-y-2">
            <p className="text-sm font-medium">
                {run.results.passed} of {run.results.total} cases passed
                {run.duration_ms !== null && (
                    <span className="text-muted-foreground font-normal">
                        {' '}
                        · {run.duration_ms} ms
                    </span>
                )}
            </p>

            <ol className="divide-border overflow-hidden rounded-md border text-xs">
                {run.results.cases.map((result) => (
                    <li
                        key={result.case}
                        className="space-y-1 border-b px-3 py-2 last:border-0"
                    >
                        <div className="flex flex-wrap items-center gap-2">
                            <span
                                className={cn(
                                    'font-mono font-medium',
                                    result.passed
                                        ? 'text-emerald-600'
                                        : 'text-destructive',
                                )}
                            >
                                {result.passed
                                    ? 'PASS'
                                    : caseLabel(result.status)}
                            </span>
                            <span className="text-muted-foreground">
                                {result.label ?? `Case ${result.case}`}
                                {!result.sample && ' (hidden)'}
                            </span>
                        </div>

                        <div className="font-mono">
                            <span className="text-muted-foreground">in </span>
                            {JSON.stringify(result.args)}
                            {result.sample && (
                                <>
                                    <span className="text-muted-foreground">
                                        {' '}
                                        want{' '}
                                    </span>
                                    {JSON.stringify(result.expected)}
                                </>
                            )}
                            <span className="text-muted-foreground"> got </span>
                            {result.has_value
                                ? JSON.stringify(result.returned)
                                : '—'}
                        </div>

                        {result.output && (
                            // Their own program's output, rendered as text. It
                            // crossed a trust boundary; React escapes it, and it
                            // is never inserted as markup.
                            <pre className="bg-muted overflow-x-auto rounded p-2 whitespace-pre-wrap">
                                {result.output}
                            </pre>
                        )}
                    </li>
                ))}
            </ol>

            {run.stderr && (
                <pre className="bg-muted overflow-x-auto rounded p-2 text-xs whitespace-pre-wrap">
                    {run.stderr}
                </pre>
            )}
        </div>
    );
}

function statusLabel(status: string): string {
    return status === 'queued'
        ? 'queued'
        : status === 'running'
          ? 'running'
          : status;
}

function caseLabel(status: string): string {
    switch (status) {
        case 'timeout':
            return 'TIME';
        case 'missing':
            return 'NONE';
        default:
            return 'FAIL';
    }
}

/**
 * Laravel's XSRF cookie, which it accepts back as a header.
 *
 * Needed because these two calls are `fetch` rather than Inertia visits — a run
 * is polled, and doing that through page visits would re-render the editor under
 * the player's cursor every second and a half.
 */
function csrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}
