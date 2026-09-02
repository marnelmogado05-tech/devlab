import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            reportReasons: string[];
            reportReasonsNeedingDetails: string[];
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
