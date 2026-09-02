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
 */
export function BoredButton({ className }: { className?: string }) {
    return (
        <Button asChild size="lg" className={cn('font-mono', className)}>
            <Link href={bored()} prefetch={false}>
                I&apos;m Bored
            </Link>
        </Button>
    );
}
