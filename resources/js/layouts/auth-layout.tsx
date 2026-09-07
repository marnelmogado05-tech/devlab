import AuthLayoutTemplate from '@/layouts/auth/auth-rail-layout';
import type { AuthLayoutProps } from '@/types';

export default function AuthLayout({
    title,
    description,
    variant,
    children,
}: AuthLayoutProps) {
    return (
        <AuthLayoutTemplate
            title={title}
            description={description}
            variant={variant}
        >
            {children}
        </AuthLayoutTemplate>
    );
}
