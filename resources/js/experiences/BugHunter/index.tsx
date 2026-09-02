import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { submit } from '@/routes/attempts';

export interface BugHunterConfiguration {
    language: string;
    mode: string;
    snippet: string;
    context?: string | null;
    prompt?: string | null;
}

/**
 * The Bug Hunter interface: numbered code, pick the guilty line.
 *
 * Presentation only. The chosen line is intent; the server decides whether it is
 * the defect, and this component is never told where the bug is — before or
 * after.
 */
export default function BugHunter({
    configuration,
    attemptId,
    readOnly = false,
    chosen = null,
}: {
    configuration: BugHunterConfiguration;
    attemptId: number;
    readOnly?: boolean;
    chosen?: string | null;
}) {
    // Split the way the server does, so line 7 here is line 7 there.
    const lines = configuration.snippet.replace(/\r\n?/g, '\n').split('\n');
    const chosenLine = chosen === null ? null : Number(chosen);

    const body = (disabled: boolean) => (
        <fieldset disabled={disabled} className="space-y-4">
            <legend className="mb-2 font-medium">
                {configuration.prompt ?? 'Which line contains the defect?'}
            </legend>

            {configuration.context && (
                <p className="text-muted-foreground text-sm">
                    <span className="font-mono text-xs tracking-widest uppercase">
                        Intent:{' '}
                    </span>
                    {configuration.context}
                </p>
            )}

            <ol className="divide-border overflow-hidden rounded-md border font-mono text-sm">
                {lines.map((line, index) => {
                    const number = index + 1;
                    const id = `line-${attemptId}-${number}`;

                    return (
                        <li key={number} className="border-b last:border-0">
                            <label
                                htmlFor={id}
                                className={cn(
                                    'flex cursor-pointer items-start gap-3 px-3 py-1',
                                    'has-[:checked]:bg-primary/10 has-[:checked]:ring-ring has-[:checked]:ring-1 has-[:checked]:ring-inset',
                                    !disabled && 'hover:bg-muted/60',
                                    disabled && 'cursor-default',
                                )}
                            >
                                <input
                                    type="radio"
                                    id={id}
                                    name="submission[line]"
                                    value={number}
                                    defaultChecked={chosenLine === number}
                                    className="sr-only"
                                />
                                {/*
                                 * The number is the label a player reasons with,
                                 * and aria-hidden is wrong here: a screen reader
                                 * user needs it to say which line they picked.
                                 */}
                                <span className="text-muted-foreground w-8 shrink-0 text-right tabular-nums select-none">
                                    {number}
                                </span>
                                <code className="whitespace-pre">
                                    {line === '' ? ' ' : line}
                                </code>
                            </label>
                        </li>
                    );
                })}
            </ol>
        </fieldset>
    );

    if (readOnly) {
        return <div className="space-y-4">{body(true)}</div>;
    }

    return (
        <Form action={submit(attemptId)} method="post" className="space-y-4">
            {({ processing, errors }) => (
                <>
                    {body(false)}

                    {errors['submission.line'] && (
                        <p role="alert" className="text-destructive text-sm">
                            Pick a line from the snippet.
                        </p>
                    )}

                    <Button type="submit" disabled={processing}>
                        {processing ? 'Checking…' : 'Accuse this line'}
                    </Button>
                </>
            )}
        </Form>
    );
}
