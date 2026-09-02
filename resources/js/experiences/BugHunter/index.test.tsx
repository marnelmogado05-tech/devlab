import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import BugHunter, { type BugHunterConfiguration } from './index';

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

const snippet = ['function add(a, b) {', '    return a - b;', '}'].join('\n');

function configuration(
    overrides: Partial<BugHunterConfiguration> = {},
): BugHunterConfiguration {
    return {
        language: 'javascript',
        mode: 'locate',
        snippet,
        context: 'Adds two numbers.',
        ...overrides,
    };
}

describe('BugHunter', () => {
    it('offers one choice per line', () => {
        render(<BugHunter configuration={configuration()} attemptId={1} />);

        expect(screen.getAllByRole('radio')).toHaveLength(3);
    });

    it('numbers lines from one, as the server counts them', () => {
        /*
         * The single most breakable thing here. The server validates and grades
         * against 1-based line numbers over the same split, so an off-by-one in
         * the display would mark correct answers wrong for every challenge at
         * once.
         */
        render(<BugHunter configuration={configuration()} attemptId={1} />);

        const values = screen
            .getAllByRole('radio')
            .map((radio) => (radio as HTMLInputElement).value);

        expect(values).toEqual(['1', '2', '3']);
    });

    it('splits the same way whatever wrote the snippet', () => {
        // A snippet authored on Windows must not number differently from the
        // same snippet authored on Linux.
        render(
            <BugHunter
                configuration={configuration({ snippet: 'a\r\nb\r\nc' })}
                attemptId={1}
            />,
        );

        expect(screen.getAllByRole('radio')).toHaveLength(3);
    });

    it('submits a line number under the expected field name', () => {
        render(<BugHunter configuration={configuration()} attemptId={1} />);

        const first = screen.getAllByRole('radio')[0] as HTMLInputElement;

        expect(first.name).toBe('submission[line]');
    });

    it('states the intent, because debugging is intent versus behaviour', () => {
        render(<BugHunter configuration={configuration()} attemptId={1} />);

        expect(screen.getByText(/Adds two numbers\./)).toBeTruthy();
    });

    it('shows every line of code, including the blank ones', () => {
        /*
         * A blank line still occupies a number. Collapsing it would shift every
         * line beneath it out of step with the server.
         */
        render(
            <BugHunter
                configuration={configuration({ snippet: 'a\n\nc' })}
                attemptId={1}
            />,
        );

        expect(screen.getAllByRole('radio')).toHaveLength(3);
    });

    it('renders code as text, never as markup', () => {
        render(
            <BugHunter
                configuration={configuration({
                    snippet: '<script>alert(1)</script>\nb\nc',
                })}
                attemptId={1}
            />,
        );

        expect(document.querySelector('script')).toBeNull();
    });

    it('offers no accusation once the attempt is closed', () => {
        render(
            <BugHunter
                configuration={configuration()}
                attemptId={1}
                readOnly
                chosen="2"
            />,
        );

        expect(screen.queryByRole('button')).toBeNull();

        const chosen = screen.getAllByRole('radio')[1] as HTMLInputElement;

        expect(chosen.checked).toBe(true);

        /*
         * The inputs are disabled by their enclosing fieldset, which is what the
         * HTML spec says bars descendants from interaction and submission. The
         * `.disabled` IDL property reflects only an element's OWN attribute, so
         * asserting it here would be checking the wrong thing and would push the
         * component towards repeating `disabled` on every input for no gain.
         */
        expect(chosen.closest('fieldset')?.disabled).toBe(true);
    });
});
