import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { submit } from '@/routes/attempts';

interface Option {
    key: string;
    text: string;
}

export interface CursedCodeConfiguration {
    language: string;
    mode: string;
    snippet: string;
    prompt?: string | null;
    options: Option[];
}

const defaultPrompts: Record<string, string> = {
    guess_output: 'What does this print?',
    explain_behaviour: 'Why does it do that?',
};

/**
 * The Cursed Code interface: a snippet and a multiple choice.
 *
 * Presentation only. The chosen option is intent — the server decides whether it
 * is right, and this component is never told the answer, before or after.
 */
export default function CursedCode({
    configuration,
    attemptId,
    readOnly = false,
    chosen = null,
}: {
    configuration: CursedCodeConfiguration;
    attemptId: number;
    readOnly?: boolean;
    chosen?: string | null;
}) {
    const prompt =
        configuration.prompt ??
        defaultPrompts[configuration.mode] ??
        'What happens?';

    return (
        <div className="space-y-4">
            <figure className="space-y-2">
                <figcaption className="text-muted-foreground font-mono text-xs tracking-widest uppercase">
                    {configuration.language}
                </figcaption>
                <pre className="bg-muted overflow-x-auto rounded-md p-4 font-mono text-sm">
                    <code>{configuration.snippet}</code>
                </pre>
            </figure>

            {readOnly ? (
                <fieldset className="space-y-2" disabled>
                    <legend className="mb-2 font-medium">{prompt}</legend>
                    {configuration.options.map((option) => (
                        <OptionRow
                            key={option.key}
                            option={option}
                            attemptId={attemptId}
                            selected={option.key === chosen}
                            readOnly
                        />
                    ))}
                </fieldset>
            ) : (
                <Form
                    action={submit(attemptId)}
                    method="post"
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <fieldset className="space-y-2">
                                <legend className="mb-2 font-medium">
                                    {prompt}
                                </legend>

                                {configuration.options.map((option) => (
                                    <OptionRow
                                        key={option.key}
                                        option={option}
                                        attemptId={attemptId}
                                    />
                                ))}

                                {errors['submission.answer'] && (
                                    <p
                                        role="alert"
                                        className="text-destructive text-sm"
                                    >
                                        Choose one of the options.
                                    </p>
                                )}
                            </fieldset>

                            <Button type="submit" disabled={processing}>
                                {processing ? 'Checking…' : 'Submit answer'}
                            </Button>
                        </>
                    )}
                </Form>
            )}
        </div>
    );
}

/**
 * A real radio input rather than a styled div: it arrives keyboard-navigable,
 * announced as a group, and submittable without JavaScript.
 */
function OptionRow({
    option,
    attemptId,
    selected = false,
    readOnly = false,
}: {
    option: Option;
    attemptId: number;
    selected?: boolean;
    readOnly?: boolean;
}) {
    const id = `option-${attemptId}-${option.key}`;

    return (
        <div
            className={cn(
                'has-[:checked]:border-ring has-[:checked]:bg-muted/50 flex items-start gap-3 rounded-md border p-3 transition-colors motion-reduce:transition-none',
                !readOnly && 'hover:border-ring/50',
            )}
        >
            <input
                type="radio"
                id={id}
                name="submission[answer]"
                value={option.key}
                defaultChecked={selected}
                disabled={readOnly}
                className="accent-primary mt-1"
            />
            <Label htmlFor={id} className="font-mono text-sm leading-relaxed">
                {option.text}
            </Label>
        </div>
    );
}
