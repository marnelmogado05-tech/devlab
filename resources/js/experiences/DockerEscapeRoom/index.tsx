import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { submit } from '@/routes/attempts';

export interface DockerEvidence {
    key: string;
    label: string;
    language: string;
    content: string;
    selectable?: boolean;
}

export interface DockerFix {
    key: string;
    text: string;
}

export interface DockerEscapeRoomConfiguration {
    symptom: string;
    evidence: DockerEvidence[];
    fixes: DockerFix[];
}

/**
 * The Docker Escape Room interface: the evidence, in tabs, and two questions.
 *
 * Every panel is here from the start. This is not a search for a hidden file —
 * a real engineer would have all of it open, and the difficulty is in reading
 * it, so hiding some would be a puzzle about the interface rather than about
 * Docker.
 *
 * Presentation only. Which line is selected is intent; whether it is the fault
 * is the server's to say, and this component is never told the answer, before or
 * after.
 */
export default function DockerEscapeRoom({
    configuration,
    attemptId,
    readOnly = false,
    chosen = null,
}: {
    configuration: DockerEscapeRoomConfiguration;
    attemptId: number;
    readOnly?: boolean;
    chosen?: string | null;
}) {
    const submitted = parseChosen(chosen);

    const [open, setOpen] = useState(
        () => submitted.evidence ?? configuration.evidence[0]?.key ?? '',
    );
    const [picked, setPicked] = useState<{
        evidence: string;
        line: number;
    } | null>(
        submitted.evidence && submitted.line
            ? { evidence: submitted.evidence, line: submitted.line }
            : null,
    );

    const panel =
        configuration.evidence.find((item) => item.key === open) ??
        configuration.evidence[0];

    const body = (disabled: boolean, processing: boolean) => (
        <fieldset disabled={disabled} className="space-y-6">
            <legend className="sr-only">Diagnose the container</legend>

            <section className="space-y-2">
                <h3 className="font-mono text-xs tracking-widest uppercase">
                    Symptom
                </h3>
                <p className="text-sm leading-relaxed">
                    {configuration.symptom}
                </p>
            </section>

            <section className="space-y-2">
                <h3 className="font-mono text-xs tracking-widest uppercase">
                    Evidence
                </h3>

                {/*
                 * A tablist rather than a stack of files: the point of the
                 * experience is reading ACROSS sources, and a page you have to
                 * scroll makes comparing two of them a memory exercise.
                 */}
                <div
                    role="tablist"
                    aria-label="Evidence"
                    className="flex flex-wrap gap-1"
                >
                    {configuration.evidence.map((item) => (
                        <button
                            key={item.key}
                            type="button"
                            role="tab"
                            aria-selected={item.key === panel?.key}
                            aria-controls={`panel-${attemptId}-${item.key}`}
                            onClick={() => setOpen(item.key)}
                            className={cn(
                                'focus-visible:ring-ring rounded-md px-3 py-1.5 font-mono text-xs focus-visible:ring-2 focus-visible:outline-none',
                                item.key === panel?.key
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {item.label}
                        </button>
                    ))}
                </div>

                {panel && (
                    <EvidencePanel
                        panel={panel}
                        attemptId={attemptId}
                        picked={
                            picked?.evidence === panel.key ? picked.line : null
                        }
                        onPick={(line) =>
                            setPicked({ evidence: panel.key, line })
                        }
                    />
                )}
            </section>

            {/*
             * The submission travels in hidden fields because the location is
             * two values chosen across tabs — the radio for line 7 of the
             * Dockerfile is unmounted the moment someone opens the compose file.
             */}
            <input
                type="hidden"
                name="submission[evidence]"
                value={picked?.evidence ?? ''}
            />
            <input
                type="hidden"
                name="submission[line]"
                value={picked?.line ?? ''}
            />

            <p
                className="text-muted-foreground font-mono text-xs"
                aria-live="polite"
            >
                {picked
                    ? `Fault: ${labelFor(configuration, picked.evidence)} line ${picked.line}`
                    : 'No fault selected'}
            </p>

            <section className="space-y-2">
                <h3 className="font-mono text-xs tracking-widest uppercase">
                    What fixes it?
                </h3>

                <div className="space-y-2">
                    {configuration.fixes.map((fix) => {
                        const id = `fix-${attemptId}-${fix.key}`;

                        return (
                            <label
                                key={fix.key}
                                htmlFor={id}
                                className={cn(
                                    'flex cursor-pointer items-start gap-3 rounded-md border px-3 py-2 text-sm',
                                    'has-[:checked]:border-primary has-[:checked]:bg-primary/5',
                                    'has-[:disabled]:cursor-default',
                                )}
                            >
                                <input
                                    type="radio"
                                    id={id}
                                    name="submission[fix]"
                                    value={fix.key}
                                    defaultChecked={submitted.fix === fix.key}
                                    className="mt-0.5"
                                />
                                <span>{fix.text}</span>
                            </label>
                        );
                    })}
                </div>
            </section>

            {!disabled && (
                <Button type="submit" disabled={processing || picked === null}>
                    Submit diagnosis
                </Button>
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

function EvidencePanel({
    panel,
    attemptId,
    picked,
    onPick,
}: {
    panel: DockerEvidence;
    attemptId: number;
    picked: number | null;
    onPick: (line: number) => void;
}) {
    // Split the way the server does, so line 7 here is line 7 there.
    const lines = panel.content.replace(/\r\n?/g, '\n').split('\n');
    const selectable = panel.selectable !== false;

    return (
        <div
            id={`panel-${attemptId}-${panel.key}`}
            role="tabpanel"
            aria-label={panel.label}
            className="space-y-2"
        >
            <p className="text-muted-foreground font-mono text-xs">
                {panel.language}
                {!selectable &&
                    ' · output, not a file — the fault is not fixed here'}
            </p>

            <ol className="divide-border overflow-x-auto rounded-md border font-mono text-xs">
                {lines.map((line, index) => {
                    const number = index + 1;

                    return (
                        <li key={number} className="border-b last:border-0">
                            {selectable ? (
                                <button
                                    type="button"
                                    onClick={() => onPick(number)}
                                    aria-pressed={picked === number}
                                    className={cn(
                                        'flex w-full items-start gap-3 px-3 py-1 text-left',
                                        'focus-visible:ring-ring focus-visible:ring-1 focus-visible:outline-none focus-visible:ring-inset',
                                        picked === number
                                            ? 'bg-primary/10 ring-ring ring-1 ring-inset'
                                            : 'hover:bg-muted/60',
                                    )}
                                >
                                    <LineNumber number={number} />
                                    <code className="whitespace-pre">
                                        {line || ' '}
                                    </code>
                                </button>
                            ) : (
                                <div className="flex items-start gap-3 px-3 py-1">
                                    <LineNumber number={number} />
                                    <code className="whitespace-pre">
                                        {line || ' '}
                                    </code>
                                </div>
                            )}
                        </li>
                    );
                })}
            </ol>
        </div>
    );
}

/**
 * Not aria-hidden: a screen reader user needs the number to say which line they
 * picked, exactly as a sighted user reads it off the gutter.
 */
function LineNumber({ number }: { number: number }) {
    return (
        <span className="text-muted-foreground w-8 shrink-0 text-right tabular-nums select-none">
            {number}
        </span>
    );
}

function labelFor(
    configuration: DockerEscapeRoomConfiguration,
    key: string,
): string {
    return (
        configuration.evidence.find((item) => item.key === key)?.label ?? key
    );
}

/**
 * Rebuild the submitted diagnosis for a closed attempt.
 *
 * Anything unreadable means nothing selected rather than a crash: a closed
 * attempt must always render, including one made against a version of the
 * challenge that no longer exists.
 */
function parseChosen(chosen: string | null): {
    evidence?: string;
    line?: number;
    fix?: string;
} {
    if (!chosen) {
        return {};
    }

    try {
        const parsed: unknown = JSON.parse(chosen);

        if (typeof parsed !== 'object' || parsed === null) {
            return {};
        }

        const value = parsed as Record<string, unknown>;

        return {
            evidence:
                typeof value.evidence === 'string' ? value.evidence : undefined,
            line: typeof value.line === 'number' ? value.line : undefined,
            fix: typeof value.fix === 'string' ? value.fix : undefined,
        };
    } catch {
        return {};
    }
}
