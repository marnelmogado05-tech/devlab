import { CheckCircle2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * The "we did the thing" strip on an auth page.
 *
 * Three pages used to render this inline as bare `text-green-600` — a raw
 * palette value with no dark counterpart, carrying its meaning in colour alone.
 * §44 is explicit that colour is never the only carrier of state, so the tick
 * says it too, and `--pass` is the token that was actually measured against
 * both grounds.
 *
 * `role="status"` rather than `role="alert"`: this is confirmation of something
 * the visitor just asked for, so it should be announced politely at the next
 * pause instead of interrupting whatever is being read.
 */
export default function AuthStatus({
    children,
    className,
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <p
            role="status"
            className={cn(
                'border-pass/40 bg-pass/10 text-pass flex items-start gap-2 rounded-[3px] border p-3 text-sm',
                className,
            )}
        >
            <CheckCircle2 className="mt-0.5 size-4 shrink-0" aria-hidden />
            <span>{children}</span>
        </p>
    );
}
