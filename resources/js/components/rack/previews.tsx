import type { ReactNode } from 'react';
import { Face } from '@/components/rack/plate';

/**
 * What each experience looks like, in about forty characters.
 *
 * These are still lives, not live data. Each one is a real fragment of the kind
 * of thing that experience actually shows — a cursed comparison, an off-by-one
 * diff, a service graph, a failing container, a divergent branch, a test column
 * — so the catalogue teaches the shape of the thing before a visitor commits to
 * opening it.
 *
 * Keyed by experience slug rather than by an `icon` column, because an icon can
 * only ever say "this is a thing"; a preview says what KIND of thing. An
 * experience with no entry here still renders — it gets a plate with no window,
 * which is honest rather than broken.
 */
const previews: Record<string, () => ReactNode> = {
    'cursed-code': CursedCode,
    'bug-hunter': BugHunter,
    'system-design-lab': SystemDesignLab,
    'docker-escape-room': DockerEscapeRoom,
    'git-simulator': GitSimulator,
    'code-arena': CodeArena,
};

export function Preview({ slug }: { slug: string }) {
    const Component = previews[slug];

    return Component ? <Component /> : null;
}

/* -------------------------------------------------------------------------- */

function CursedCode() {
    return (
        <Face>
            <div className="text-muted-foreground">
                // what does this print?
            </div>
            <div className="border-warn bg-warn/10 -mx-3 border-l-2 pr-3 pl-[calc(0.75rem-2px)]">
                <span className="text-info">$x</span> ={' '}
                <span className="text-pass">&apos;0&apos;</span>;{' '}
                <span className="text-info">if</span> (
                <span className="text-info">$x</span> =={' '}
                <span className="text-pass">0</span>) …
            </div>
        </Face>
    );
}

/**
 * The status gutter's first appearance. The left column carries STATE — added,
 * removed, unchanged — not a decorative 01/02/03. It is the one structural
 * device that repeats across the app, and it always means the same thing.
 */
function BugHunter() {
    return (
        <Face className="grid grid-cols-[1.1rem_1fr] gap-x-2">
            <span className="text-fail text-center">−</span>
            <span>
                <span className="text-info">for</span> ($i=0; $i
                <span className="text-fail">&lt;=</span>$n; $i++)
            </span>

            <span className="text-pass text-center">+</span>
            <span>
                <span className="text-info">for</span> ($i=0; $i
                <span className="text-pass">&lt;</span>$n; $i++)
            </span>

            <span aria-hidden> </span>
            <span className="text-muted-foreground">off by one, line 14</span>
        </Face>
    );
}

function SystemDesignLab() {
    return (
        <Face className="leading-none">
            <svg
                viewBox="0 0 220 62"
                className="mx-auto h-auto w-full max-w-[320px]"
            >
                <g stroke="var(--border)" strokeWidth="1.5">
                    <line x1="34" y1="31" x2="96" y2="31" />
                    <line x1="124" y1="31" x2="176" y2="14" />
                    <line x1="124" y1="31" x2="176" y2="48" />
                </g>
                <g fill="var(--raised)" strokeWidth="1">
                    <rect
                        x="6"
                        y="22"
                        width="28"
                        height="18"
                        stroke="var(--info)"
                    />
                    <rect
                        x="96"
                        y="22"
                        width="28"
                        height="18"
                        stroke="var(--pass)"
                    />
                    <rect
                        x="176"
                        y="5"
                        width="28"
                        height="18"
                        stroke="var(--border)"
                    />
                    <rect
                        x="176"
                        y="39"
                        width="28"
                        height="18"
                        stroke="var(--warn)"
                    />
                </g>
                <g
                    fill="var(--muted-foreground)"
                    fontSize="8"
                    fontFamily="monospace"
                    textAnchor="middle"
                >
                    <text x="20" y="34">
                        cdn
                    </text>
                    <text x="110" y="34">
                        lb
                    </text>
                    <text x="190" y="17">
                        app
                    </text>
                    <text x="190" y="51">
                        db
                    </text>
                </g>
            </svg>
        </Face>
    );
}

function DockerEscapeRoom() {
    return (
        <Face>
            <div>
                <span className="text-muted-foreground">$</span> docker run
                app:latest
            </div>
            <div className="text-fail">exec /entrypoint: no such file</div>
            <div>
                <span className="text-muted-foreground">$</span>{' '}
                <span className="bg-foreground inline-block h-[1em] w-[0.5em] align-text-bottom" />
            </div>
        </Face>
    );
}

function GitSimulator() {
    return (
        <Face className="leading-none">
            <svg
                viewBox="0 0 220 62"
                className="mx-auto h-auto w-full max-w-[320px]"
            >
                <path
                    d="M14 46 H70 M70 46 C96 46 96 18 122 18 H162"
                    fill="none"
                    stroke="var(--border)"
                    strokeWidth="1.5"
                />
                <path
                    d="M70 46 H178"
                    fill="none"
                    stroke="var(--border)"
                    strokeWidth="1.5"
                />
                <path
                    d="M162 18 C186 18 186 46 200 46"
                    fill="none"
                    stroke="var(--xp)"
                    strokeWidth="1.5"
                />
                <g fill="var(--card)" strokeWidth="1.5" r="4.5">
                    <circle
                        cx="14"
                        cy="46"
                        r="4.5"
                        stroke="var(--muted-foreground)"
                    />
                    <circle
                        cx="70"
                        cy="46"
                        r="4.5"
                        stroke="var(--muted-foreground)"
                    />
                    <circle cx="122" cy="18" r="4.5" stroke="var(--info)" />
                    <circle cx="162" cy="18" r="4.5" stroke="var(--info)" />
                    <circle cx="200" cy="46" r="4.5" stroke="var(--xp)" />
                </g>
                <text
                    x="122"
                    y="10"
                    fill="var(--muted-foreground)"
                    fontSize="8"
                    fontFamily="monospace"
                    textAnchor="middle"
                >
                    feat
                </text>
            </svg>
        </Face>
    );
}

/**
 * The same gutter as Bug Hunter, carrying a different alphabet of states —
 * which is the point of having one device rather than six.
 */
function CodeArena() {
    return (
        <Face className="grid grid-cols-[1.1rem_1fr] gap-x-2">
            <span className="text-pass text-center">✓</span>
            <span>
                case 0 <span className="text-muted-foreground">· 2ms</span>
            </span>

            <span className="text-fail text-center">✗</span>
            <span>
                case 1{' '}
                <span className="text-muted-foreground">· want 7, got 0</span>
            </span>

            <span className="text-muted-foreground text-center">·</span>
            <span className="text-muted-foreground">case 2 (hidden)</span>
        </Face>
    );
}
