import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

/*
 * Contrast is a requirement, not a preference (§44), so it is asserted rather
 * than eyeballed.
 *
 * This exists because eyeballing missed four separate invisibility bugs in one
 * pass: a hover state that repainted a yellow button to near-black behind
 * near-black text, an Alert whose destructive variant painted text in the
 * colour meant for sitting ON destructive, a segmented control that hovered to
 * `text-black` with no dark-theme counterpart, and secondary text on a muted
 * track at 4.19:1. Three of the four were only visible in one theme, which is
 * exactly what a human reviewer skims past.
 *
 * The pairs below are every foreground/surface combination the system can
 * actually produce — including the ones that are easy to forget, like a dim
 * label on the muted track a segmented control sits on, or a semantic colour
 * inside the recessed preview window rather than on the page.
 */

// Resolved from the working directory rather than `import.meta.url`: Vitest
// serves modules over http, so that URL is not a file: one here.
const css = readFileSync(
    resolve(process.cwd(), 'resources/css/app.css'),
    'utf8',
);

/** The hex tokens declared in one theme block. */
function theme(selector: string): Record<string, string> {
    const start = css.indexOf(`${selector} {`);
    const end = css.indexOf('\n}', start);

    expect(start, `${selector} block not found in app.css`).toBeGreaterThan(-1);

    return Object.fromEntries(
        [
            ...css.slice(start, end).matchAll(/(--[a-z-]+):\s*(#[0-9a-f]{6})/g),
        ].map((match) => [match[1], match[2]]),
    );
}

function luminance(hex: string): number {
    const channels = [1, 3, 5]
        .map((i) => parseInt(hex.slice(i, i + 2), 16) / 255)
        .map((c) => (c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));

    return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
}

function contrast(a: string, b: string): number {
    const [x, y] = [luminance(a), luminance(b)];

    return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
}

/** [foreground, surface, what it is, minimum ratio] */
const pairs: [string, string, string, number][] = [
    ['--foreground', '--background', 'body text on the page', 4.5],
    ['--foreground', '--card', 'body text on a plate', 4.5],
    ['--foreground', '--face', 'body text in a preview window', 4.5],
    ['--foreground', '--raised', 'body text on a raised cell', 4.5],
    ['--muted-foreground', '--background', 'secondary text on the page', 4.5],
    ['--muted-foreground', '--card', 'secondary text on a plate', 4.5],
    ['--muted-foreground', '--face', 'secondary text in a window', 4.5],
    // The segmented controls sit on this one, and it is the tightest surface
    // in the system — it is why --muted-foreground is #94A1B1 and not dimmer.
    ['--muted-foreground', '--muted', 'secondary text on a muted track', 4.5],
    ['--go-foreground', '--go', 'the pill at rest', 4.5],
    ['--go-foreground', '--go-hover', 'the pill under the cursor', 4.5],
    ['--primary-foreground', '--primary', 'a primary button', 4.5],
    ['--secondary-foreground', '--secondary', 'a secondary button', 4.5],
    ['--accent-foreground', '--accent', 'a ghost button being hovered', 4.5],
    [
        '--destructive-foreground',
        '--destructive',
        'text on a destructive fill',
        4.5,
    ],
    ['--destructive', '--background', 'a field error on the page', 4.5],
    ['--destructive', '--card', 'a field error on a plate', 4.5],
    ['--pass', '--background', 'pass on the page', 4.5],
    ['--pass', '--card', 'pass on a plate', 4.5],
    ['--pass', '--face', 'pass in a window', 4.5],
    ['--fail', '--background', 'fail on the page', 4.5],
    ['--fail', '--card', 'fail on a plate', 4.5],
    ['--fail', '--face', 'fail in a window', 4.5],
    ['--warn', '--background', 'warn on the page', 4.5],
    ['--warn', '--card', 'warn on a plate', 4.5],
    ['--warn', '--face', 'warn in a window', 4.5],
    ['--info', '--background', 'info on the page', 4.5],
    ['--info', '--card', 'info on a plate', 4.5],
    ['--info', '--face', 'info in a window', 4.5],
    ['--xp', '--background', 'xp on the page', 4.5],
    ['--xp', '--card', 'xp on a plate', 4.5],
    // Non-text, so 3:1 — but a focus ring nobody can see is not a focus ring.
    ['--ring', '--background', 'the focus ring against the page', 3],
    ['--ring', '--card', 'the focus ring against a plate', 3],
];

describe.each([
    ['dark — the designed theme', '.dark'],
    ['light — the port', ':root'],
])('%s', (_name, selector) => {
    const tokens = theme(selector);

    it.each(pairs)(
        '%s on %s reads: %s',
        (foreground, surface, _what, minimum) => {
            expect(
                tokens[foreground],
                `${foreground} is undeclared`,
            ).toBeDefined();
            expect(tokens[surface], `${surface} is undeclared`).toBeDefined();

            expect(
                contrast(tokens[foreground], tokens[surface]),
            ).toBeGreaterThanOrEqual(minimum);
        },
    );
});

describe('the rules the palette encodes', () => {
    it('keeps a plate lighter than the page it sits on', () => {
        // Rule 1 of the rack: a plate is set ON the ground, not cut into it.
        const dark = theme('.dark');

        expect(luminance(dark['--card'])).toBeGreaterThan(
            luminance(dark['--background']),
        );
    });

    it('keeps the preview window darker than the plate around it', () => {
        // The one exception, because it is a window rather than a panel.
        const dark = theme('.dark');

        expect(luminance(dark['--face'])).toBeLessThan(
            luminance(dark['--card']),
        );
    });

    it('never lets the accent double as the primary button colour', () => {
        /*
         * The rule that keeps "I'm Bored" unmistakable. When these were the
         * same token, every submit and sign-in button in the application wore
         * the accent and the one control that matters stopped standing out.
         */
        for (const selector of ['.dark', ':root']) {
            const tokens = theme(selector);

            expect(tokens['--go']).not.toBe(tokens['--primary']);
        }
    });
});
