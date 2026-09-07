import type { ReactNode } from 'react';
import type { BreadcrumbItem } from '@/types/navigation';

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

/**
 * Which side of the door an auth page is on.
 *
 * `guest` is someone who has not signed in yet — log in, register, forgot and
 * reset password. `secure` is someone who has, and is being asked to prove it
 * again: confirm password, the two-factor challenge, email verification. The
 * two want opposite copy beside the form, so the layout branches on this rather
 * than pitching an account at people who already have one.
 */
export type AuthVariant = 'guest' | 'secure';

export type AuthLayoutProps = {
    children?: ReactNode;
    title?: string;
    description?: string;
    variant?: AuthVariant;
};
