import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { submit } from '@/routes/attempts';

export interface SystemDesignLabRequirement {
    key: string;
    text: string;
}

export interface SystemDesignLabOption {
    key: string;
    text: string;
}

export interface SystemDesignLabSlot {
    key: string;
    label: string;
    hint?: string | null;
    options: SystemDesignLabOption[];
}

export interface SystemDesignLabConfiguration {
    scenario: string;
    requirements: SystemDesignLabRequirement[];
    slots: SystemDesignLabSlot[];
}

/**
 * The System Design Lab interface: a brief, a list of requirements, and one
 * decision per slot.
 *
 * There is deliberately no live "requirements met" indicator. Computing one
 * needs the rubric, and shipping the rubric to the browser to compute it would
 * hand over the answer key. The design is scored once, on the server, when it is
 * submitted — the requirements list is a statement of the goal, not a scoreboard.
 *
 * Presentation only. What is tracked here is which radio is checked, so the
 * player can see their design as a whole before committing to it.
 */
export default function SystemDesignLab({
    configuration,
    attemptId,
    readOnly = false,
    chosen = null,
}: {
    configuration: SystemDesignLabConfiguration;
    attemptId: number;
    readOnly?: boolean;
    chosen?: string | null;
}) {
    const [choices, setChoices] = useState<Record<string, string>>(() =>
        parseChosen(chosen),
    );

    const decided = configuration.slots.filter(
        (slot) => choices[slot.key] !== undefined,
    ).length;

    const body = (disabled: boolean, processing: boolean) => (
        <fieldset disabled={disabled} className="space-y-6">
            <legend className="sr-only">Design the system</legend>

            <section
                aria-labelledby={`brief-${attemptId}`}
                className="space-y-2"
            >
                <h3
                    id={`brief-${attemptId}`}
                    className="font-mono text-xs tracking-widest uppercase"
                >
                    Brief
                </h3>
                <p className="text-sm leading-relaxed">
                    {configuration.scenario}
                </p>
            </section>

            <section
                aria-labelledby={`requirements-${attemptId}`}
                className="space-y-2"
            >
                <h3
                    id={`requirements-${attemptId}`}
                    className="font-mono text-xs tracking-widest uppercase"
                >
                    It must
                </h3>
                <ul className="space-y-1">
                    {configuration.requirements.map((requirement) => (
                        <li
                            key={requirement.key}
                            className="text-muted-foreground flex gap-2 text-sm"
                        >
                            <span aria-hidden="true">·</span>
                            <span>{requirement.text}</span>
                        </li>
                    ))}
                </ul>
            </section>

            <div className="space-y-4">
                {configuration.slots.map((slot) => (
                    <Slot
                        key={slot.key}
                        slot={slot}
                        attemptId={attemptId}
                        chosen={choices[slot.key]}
                        onChoose={(option) =>
                            setChoices((current) => ({
                                ...current,
                                [slot.key]: option,
                            }))
                        }
                    />
                ))}
            </div>

            {!disabled && (
                <div className="flex flex-wrap items-center gap-3">
                    <Button
                        type="submit"
                        disabled={
                            processing || decided < configuration.slots.length
                        }
                    >
                        Submit design
                    </Button>

                    {/*
                     * A count, not a verdict. It says whether the form is
                     * complete — which the client legitimately knows — and says
                     * nothing about whether the design is any good, which it
                     * does not.
                     */}
                    <p
                        className="text-muted-foreground font-mono text-xs"
                        aria-live="polite"
                    >
                        {decided} of {configuration.slots.length} decided
                    </p>
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

function Slot({
    slot,
    attemptId,
    chosen,
    onChoose,
}: {
    slot: SystemDesignLabSlot;
    attemptId: number;
    chosen: string | undefined;
    onChoose: (option: string) => void;
}) {
    return (
        <fieldset className="space-y-2">
            <legend className="text-sm font-medium">{slot.label}</legend>

            {slot.hint && (
                <p className="text-muted-foreground text-xs">{slot.hint}</p>
            )}

            <div className="grid gap-2 sm:grid-cols-2">
                {slot.options.map((option) => {
                    const id = `slot-${attemptId}-${slot.key}-${option.key}`;

                    return (
                        <label
                            key={option.key}
                            htmlFor={id}
                            className={cn(
                                'flex cursor-pointer items-start gap-3 rounded-md border px-3 py-2 text-sm',
                                'has-[:checked]:border-primary has-[:checked]:bg-primary/5',
                                'has-[:focus-visible]:ring-ring has-[:focus-visible]:ring-2',
                                'has-[:disabled]:cursor-default',
                            )}
                        >
                            <input
                                type="radio"
                                id={id}
                                name={`submission[choices][${slot.key}]`}
                                value={option.key}
                                checked={chosen === option.key}
                                onChange={() => onChoose(option.key)}
                                className="mt-0.5"
                            />
                            <span>{option.text}</span>
                        </label>
                    );
                })}
            </div>
        </fieldset>
    );
}

/**
 * Rebuild the submitted design for a closed attempt.
 *
 * `chosen` arrives as the JSON the player submitted. A malformed or absent one
 * means an empty board rather than a crash — a closed attempt must always
 * render, including one from a version of the challenge that no longer exists.
 */
function parseChosen(chosen: string | null): Record<string, string> {
    if (!chosen) {
        return {};
    }

    try {
        const parsed: unknown = JSON.parse(chosen);

        if (typeof parsed !== 'object' || parsed === null) {
            return {};
        }

        return Object.fromEntries(
            Object.entries(parsed as Record<string, unknown>)
                .filter(([, value]) => typeof value === 'string')
                .map(([key, value]) => [key, value as string]),
        );
    } catch {
        return {};
    }
}
