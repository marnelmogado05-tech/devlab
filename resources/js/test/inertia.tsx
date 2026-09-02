import { vi } from 'vitest';

/**
 * A stand-in for Inertia's `<Form>`.
 *
 * The real one needs a running Inertia app to submit through. These are
 * component tests: what is being checked is what the component RENDERS and what
 * it would send — the round trip is already covered by the feature tests, which
 * exercise the actual routes.
 *
 * The mock keeps the real `<form>` element and the render-prop signature, so
 * fields, labels and the submit button are exercised exactly as they ship.
 */
export function mockInertiaForm() {
    vi.mock('@inertiajs/react', async (importOriginal) => {
        const actual = await importOriginal<Record<string, unknown>>();

        return {
            ...actual,
            Form: ({
                children,
                action,
                method,
                ...props
            }: {
                children:
                    | React.ReactNode
                    | ((state: {
                          processing: boolean;
                          errors: Record<string, string>;
                      }) => React.ReactNode);
                action?: unknown;
                method?: string;
                [key: string]: unknown;
            }) => (
                <form
                    // Exposed so a test can assert where a submission would go
                    // without needing Inertia to route it.
                    data-testid="inertia-form"
                    data-method={method}
                    data-action={
                        typeof action === 'string'
                            ? action
                            : JSON.stringify(action)
                    }
                    {...(props as Record<string, unknown>)}
                >
                    {typeof children === 'function'
                        ? children({ processing: false, errors: {} })
                        : children}
                </form>
            ),
        };
    });
}
