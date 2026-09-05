import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * A plate: one experience's faceplate in the rack.
 *
 * The chassis never varies. Same border, same 3px corner, the name at top-left,
 * the count at top-right, no drop shadow anywhere — separation comes from the
 * value step between the plate and the page plus a lighter top edge, the way a
 * bevelled faceplate reads.
 *
 * What varies is the window: {@see Face}. Six identical cards become six
 * recognisably different instruments without a single decorative flourish,
 * which is what §46's "each experience should look like a different toy, sharing
 * a chassis" actually asks for.
 *
 * A plate is LIGHTER than the page behind it. That inverts the usual dark-mode
 * habit of darker cards, and it is the rule the whole surface is built on: a
 * plate is something set on the rack, not a hole cut into it.
 */
export function Plate({
    href,
    name,
    count,
    tagline,
    minutes,
    children,
}: {
    href: string;
    name: string;
    count: number;
    tagline: string | null;
    minutes: number;
    children: ReactNode;
}) {
    return (
        <article className="bg-card border-border border-t-bevel hover:border-foreground/30 relative flex h-full flex-col gap-3 rounded-[3px] border p-4 transition-colors">
            <div className="flex items-baseline gap-3">
                <h2 className="flex-1 text-base font-semibold">
                    {/*
                     * One anchor, stretched over the plate by the pseudo-element
                     * rather than wrapping it. That keeps a single tab stop and
                     * a single announcement per plate — a card whose every child
                     * is a link reads as a pile of links to a screen reader.
                     */}
                    <Link
                        href={href}
                        className="focus-visible:ring-ring rounded-sm after:absolute after:inset-0 focus-visible:ring-2 focus-visible:outline-none"
                    >
                        {name}
                    </Link>
                </h2>

                <span className="text-muted-foreground font-mono text-xs">
                    {count}
                </span>
            </div>

            {children}

            {tagline && (
                <p className="text-muted-foreground text-sm">{tagline}</p>
            )}

            <dl className="text-muted-foreground mt-auto flex flex-wrap gap-x-4 font-mono text-xs">
                <div className="flex gap-1.5">
                    <dt className="sr-only">Challenges</dt>
                    <dd>
                        {count} {count === 1 ? 'challenge' : 'challenges'}
                    </dd>
                </div>
                <div className="flex gap-1.5">
                    <dt className="sr-only">Typical length</dt>
                    <dd>~{minutes} min</dd>
                </div>
            </dl>
        </article>
    );
}

/**
 * The recessed window a plate previews its experience through.
 *
 * The one surface in the system that is DARKER than the ground, because it is a
 * window rather than a panel — and square, because radius is hierarchy here and
 * data cells do not get corners.
 *
 * Marked `aria-hidden`: every preview is a still life of what the experience
 * looks like, and the plate's heading, tagline and counts already say
 * everything a screen reader needs. Announcing a decorative fragment of PHP
 * between the name and the description would be noise.
 */
export function Face({
    className,
    children,
}: {
    className?: string;
    children: ReactNode;
}) {
    return (
        <div
            aria-hidden="true"
            className={cn(
                'bg-face border-border/70 overflow-x-auto border p-3 font-mono text-[0.72rem] leading-[1.7]',
                className,
            )}
        >
            {children}
        </div>
    );
}
