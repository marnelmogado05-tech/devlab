import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import DockerEscapeRoom, { type DockerEscapeRoomConfiguration } from './index';

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
    overrides: Partial<DockerEscapeRoomConfiguration> = {},
): DockerEscapeRoomConfiguration {
    return {
        symptom: 'It says it is listening. Nothing reaches the port.',
        evidence: [
            {
                key: 'dockerfile',
                label: 'Dockerfile',
                language: 'dockerfile',
                content:
                    'FROM node:22-alpine\nWORKDIR /app\nCMD ["node", "s.js"]',
            },
            {
                key: 'compose',
                label: 'docker-compose.yml',
                language: 'yaml',
                content: 'services:\n    api:\n        build: .',
            },
            {
                key: 'logs',
                label: 'Container logs',
                language: 'text',
                selectable: false,
                content: 'listening on 127.0.0.1:3000',
            },
        ],
        fixes: [
            { key: 'bind_all', text: 'Bind to 0.0.0.0' },
            { key: 'publish', text: 'Publish the port' },
            { key: 'expose', text: 'Add EXPOSE' },
        ],
        ...overrides,
    };
}

function hidden(name: string): HTMLInputElement {
    return document.querySelector(
        `input[name="submission[${name}]"]`,
    ) as HTMLInputElement;
}

describe('DockerEscapeRoom', () => {
    it('states the symptom and offers every panel as a tab', () => {
        render(
            <DockerEscapeRoom configuration={configuration()} attemptId={1} />,
        );

        expect(screen.getByText(/Nothing reaches the port/)).toBeTruthy();
        expect(screen.getAllByRole('tab')).toHaveLength(3);
    });

    it('shows one panel at a time and switches between them', () => {
        /*
         * A tablist rather than a stack: the experience is reading ACROSS
         * sources, and a long scroll makes comparing two of them a memory
         * exercise.
         */
        render(
            <DockerEscapeRoom configuration={configuration()} attemptId={1} />,
        );

        expect(screen.getByText('FROM node:22-alpine')).toBeTruthy();
        expect(screen.queryByText('services:')).toBeNull();

        fireEvent.click(
            screen.getByRole('tab', { name: 'docker-compose.yml' }),
        );

        expect(screen.getByText('services:')).toBeTruthy();
        expect(screen.queryByText('FROM node:22-alpine')).toBeNull();
    });

    it('numbers lines from one, as the server counts them', () => {
        // An off-by-one here would mark correct answers wrong for every
        // challenge at once.
        render(
            <DockerEscapeRoom configuration={configuration()} attemptId={1} />,
        );

        const lines = screen.getAllByRole('button', { pressed: false });

        expect(lines[0].textContent).toContain('1');
        expect(lines[0].textContent).toContain('FROM node:22-alpine');
    });

    it('offers no line to pick on evidence that is output rather than a file', () => {
        // Logs are read, not fixed. Offering a line number there invites an
        // answer to the wrong question.
        render(
            <DockerEscapeRoom configuration={configuration()} attemptId={1} />,
        );

        fireEvent.click(screen.getByRole('tab', { name: 'Container logs' }));

        expect(
            screen.queryByRole('button', { name: /127\.0\.0\.1/ }),
        ).toBeNull();
        expect(screen.getByText(/output, not a file/)).toBeTruthy();
    });

    it('carries the location in hidden fields, because it is chosen across tabs', () => {
        /*
         * The radio for line 3 of the Dockerfile unmounts the moment someone
         * opens the compose file, so the selection cannot live in the inputs
         * themselves.
         */
        render(
            <DockerEscapeRoom configuration={configuration()} attemptId={1} />,
        );

        expect(hidden('evidence').value).toBe('');

        fireEvent.click(screen.getByRole('button', { name: /WORKDIR/ }));

        expect(hidden('evidence').value).toBe('dockerfile');
        expect(hidden('line').value).toBe('2');
    });

    it('keeps a selection made on another tab', () => {
        render(
            <DockerEscapeRoom configuration={configuration()} attemptId={1} />,
        );

        fireEvent.click(screen.getByRole('button', { name: /WORKDIR/ }));
        fireEvent.click(
            screen.getByRole('tab', { name: 'docker-compose.yml' }),
        );

        expect(hidden('evidence').value).toBe('dockerfile');
        expect(screen.getByText(/Dockerfile line 2/)).toBeTruthy();
    });

    it('moves the selection when a line on a different panel is picked', () => {
        render(
            <DockerEscapeRoom configuration={configuration()} attemptId={1} />,
        );

        fireEvent.click(screen.getByRole('button', { name: /WORKDIR/ }));
        fireEvent.click(
            screen.getByRole('tab', { name: 'docker-compose.yml' }),
        );
        fireEvent.click(screen.getByRole('button', { name: /build:/ }));

        expect(hidden('evidence').value).toBe('compose');
        expect(hidden('line').value).toBe('3');
    });

    it('will not submit without a location', () => {
        render(
            <DockerEscapeRoom configuration={configuration()} attemptId={1} />,
        );

        const button = screen.getByRole('button', {
            name: /submit diagnosis/i,
        }) as HTMLButtonElement;

        expect(button.disabled).toBe(true);

        fireEvent.click(screen.getByRole('button', { name: /WORKDIR/ }));

        expect(button.disabled).toBe(false);
    });

    it('renders evidence as text, never as markup', () => {
        render(
            <DockerEscapeRoom
                configuration={configuration({
                    evidence: [
                        {
                            key: 'dockerfile',
                            label: 'Dockerfile',
                            language: 'dockerfile',
                            content: '<img src=x onerror="alert(1)">\nb\nc',
                        },
                        {
                            key: 'compose',
                            label: 'compose',
                            language: 'yaml',
                            content: 'a\nb\nc',
                        },
                    ],
                })}
                attemptId={1}
            />,
        );

        expect(document.querySelector('img')).toBeNull();
    });

    it('rebuilds a closed diagnosis, with no way to change it', () => {
        render(
            <DockerEscapeRoom
                configuration={configuration()}
                attemptId={1}
                readOnly
                chosen={JSON.stringify({
                    evidence: 'compose',
                    line: 3,
                    fix: 'publish',
                })}
            />,
        );

        expect(screen.getByText(/docker-compose\.yml line 3/)).toBeTruthy();
        expect(
            (screen.getByLabelText('Publish the port') as HTMLInputElement)
                .checked,
        ).toBe(true);
        expect(
            screen.queryByRole('button', { name: /submit diagnosis/i }),
        ).toBeNull();
    });

    it('renders a closed attempt whose submission cannot be read', () => {
        render(
            <DockerEscapeRoom
                configuration={configuration()}
                attemptId={1}
                readOnly
                chosen="not json"
            />,
        );

        expect(screen.getByText('No fault selected')).toBeTruthy();
    });
});
