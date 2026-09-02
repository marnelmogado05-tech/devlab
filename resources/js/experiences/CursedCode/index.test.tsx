import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import CursedCode, { type CursedCodeConfiguration } from './index';

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
    overrides: Partial<CursedCodeConfiguration> = {},
): CursedCodeConfiguration {
    return {
        language: 'php',
        mode: 'guess_output',
        snippet: 'var_dump(0.1 + 0.2 == 0.3);',
        options: [
            { key: 'a', text: 'bool(true)' },
            { key: 'b', text: 'bool(false)' },
        ],
        ...overrides,
    };
}

describe('CursedCode', () => {
    it('shows the snippet and its language', () => {
        render(<CursedCode configuration={configuration()} attemptId={1} />);

        expect(screen.getByText('var_dump(0.1 + 0.2 == 0.3);')).toBeTruthy();
        expect(screen.getByText('php')).toBeTruthy();
    });

    it('offers every option as a radio', () => {
        /*
         * Real radio inputs rather than styled divs: they arrive
         * keyboard-navigable, announced as a group, and submittable without
         * JavaScript. A test on the ROLE is what stops that regressing into
         * clickable divs.
         */
        render(<CursedCode configuration={configuration()} attemptId={1} />);

        const radios = screen.getAllByRole('radio');

        expect(radios).toHaveLength(2);
        expect(screen.getByLabelText('bool(false)')).toBeTruthy();
    });

    it('submits the option key, not the option text', () => {
        // The server compares against `solution.answer`, which is a key.
        render(<CursedCode configuration={configuration()} attemptId={1} />);

        const chosen = screen.getByLabelText('bool(false)') as HTMLInputElement;

        expect(chosen.value).toBe('b');
        expect(chosen.name).toBe('submission[answer]');
    });

    it('uses a default prompt per mode when the author gave none', () => {
        render(<CursedCode configuration={configuration()} attemptId={1} />);
        expect(screen.getByText('What does this print?')).toBeTruthy();

        cleanupAndRender(
            <CursedCode
                configuration={configuration({ mode: 'explain_behaviour' })}
                attemptId={1}
            />,
        );
        expect(screen.getByText('Why does it do that?')).toBeTruthy();
    });

    it('prefers the author prompt when there is one', () => {
        render(
            <CursedCode
                configuration={configuration({ prompt: 'What on earth?' })}
                attemptId={1}
            />,
        );

        expect(screen.getByText('What on earth?')).toBeTruthy();
    });

    it('never renders a submit control once the attempt is closed', () => {
        render(
            <CursedCode
                configuration={configuration()}
                attemptId={1}
                readOnly
                chosen="b"
            />,
        );

        expect(screen.queryByRole('button')).toBeNull();
    });

    it('shows what was chosen, disabled, after the attempt closes', () => {
        render(
            <CursedCode
                configuration={configuration()}
                attemptId={1}
                readOnly
                chosen="b"
            />,
        );

        const chosen = screen.getByLabelText('bool(false)') as HTMLInputElement;

        expect(chosen.checked).toBe(true);
        expect(chosen.disabled).toBe(true);
    });

    it('renders a snippet as text, never as markup', () => {
        /*
         * Snippets are content, and will eventually be community-submitted.
         * React escapes by default — this asserts nobody has since reached for
         * dangerouslySetInnerHTML to get syntax highlighting working.
         */
        render(
            <CursedCode
                configuration={configuration({
                    snippet: '<img src=x onerror="alert(1)">',
                })}
                attemptId={1}
            />,
        );

        expect(screen.getByText('<img src=x onerror="alert(1)">')).toBeTruthy();
        expect(document.querySelector('img')).toBeNull();
    });
});

/** Render after clearing, for the two-case prompt test. */
function cleanupAndRender(element: React.ReactElement) {
    document.body.innerHTML = '';
    render(element);
}
