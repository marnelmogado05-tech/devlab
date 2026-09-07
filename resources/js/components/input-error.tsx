import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/**
 * A field's error message.
 *
 * `text-destructive` rather than the starter kit's `text-red-600 dark:text-red-400`:
 * those are raw palette values that answer to nothing, and the whole point of
 * having a token is that the two themes stay one system. The token is also the
 * pair that was measured — 5.7:1 on the dark ground, and a darker red on paper.
 *
 * `role="alert"` because §44 requires errors to be announced, not merely
 * rendered. The element only exists when there is a message, so it enters the
 * accessibility tree exactly when something needs saying. Give it an `id` and
 * point the field's `aria-describedby` at it, or the announcement is orphaned
 * from the input it describes.
 */
export default function InputError({
    message,
    className = '',
    ...props
}: HTMLAttributes<HTMLParagraphElement> & { message?: string }) {
    return message ? (
        <p
            role="alert"
            {...props}
            className={cn('text-destructive text-sm', className)}
        >
            {message}
        </p>
    ) : null;
}
