import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
};

/**
 * An experience as the sidebar needs it: enough to link to it and name it.
 *
 * Shared on every request from the published catalogue, so the navigation
 * cannot list something the catalogue does not.
 */
export type NavExperience = {
    slug: string;
    name: string;
    /** A lucide icon name, resolved by `experienceIcon`. */
    icon: string | null;
};
