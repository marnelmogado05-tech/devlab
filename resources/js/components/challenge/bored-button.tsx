import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { bored } from '@/routes';

/**
 * The product, as a button.
 *
 * A plain link to a GET: pressing it decides something but creates nothing, so a
 * refresh is harmless. The copy promises no choice, because not choosing is the
 * point.
 *
 * The only fully-rounded thing in the interface, and the only thing wearing the
 * accent. Radius carries hierarchy here — plates are 3px, the data cells inside
 * them are square, and the pill is the one shape that says "press me". If a
 * second pill or a second yellow control appears on a screen, one of them is
 * wrong.
 */
export function BoredButton({ className }: { className?: string }) {
    return (
        <Button
            asChild
            className={cn(
                // `go`, not `primary`. Every other button in the application is
                // primary; this is the only thing that gets the accent.
                'bg-go text-go-foreground rounded-full px-5 font-semibold',
                /*
                 * The hover pair is stated explicitly, both halves of it.
                 * `Button`'s default variant carries `hover:bg-primary/90`, and
                 * tailwind-merge does NOT drop it when the base `bg-primary` is
                 * overridden — a `hover:` utility is not a conflict with a base
                 * one. Left alone it repainted the pill to `--primary` on
                 * hover, which in the light theme is near-black behind
                 * near-black text. Naming the hover background and re-asserting
                 * the text colour is what makes the override total.
                 */
                'hover:bg-go-hover hover:text-go-foreground',
                // Presses move; nothing else in the system does. Reduced motion
                // is handled globally in app.css.
                'transition-[background-color,transform] active:translate-y-px',
                className,
            )}
        >
            <Link href={bored()} prefetch={false}>
                I&apos;m Bored
            </Link>
        </Button>
    );
}
