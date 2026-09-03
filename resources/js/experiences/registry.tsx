import { lazy, Suspense, type ComponentType } from 'react';
import { Skeleton } from '@/components/ui/skeleton';

/**
 * Which React module renders which experience, keyed by experience slug.
 *
 * Lazy, so a visitor playing Cursed Code never downloads the Git Simulator. The
 * map is the only place an experience's frontend is wired in: adding one is an
 * entry here plus a directory, and touches nothing else.
 */
const modules: Record<
    string,
    () => Promise<{ default: ComponentType<ExperienceModuleProps> }>
> = {
    'cursed-code': () => import('@/experiences/CursedCode'),
    'bug-hunter': () => import('@/experiences/BugHunter'),
    'system-design-lab': () => import('@/experiences/SystemDesignLab'),
    'docker-escape-room': () => import('@/experiences/DockerEscapeRoom'),
    'git-simulator': () => import('@/experiences/GitSimulator'),
};

export interface ExperienceModuleProps {
    // The shape is per-experience and validated server-side, so it arrives here
    // already conforming to that experience's documented schema.
    configuration: never;
    attemptId: number;
    readOnly?: boolean;
    chosen?: string | null;
}

export function hasExperienceModule(slug: string): boolean {
    return slug in modules;
}

export function ExperienceModule({
    slug,
    ...props
}: { slug: string } & ExperienceModuleProps) {
    const loader = modules[slug];

    if (!loader) {
        return null;
    }

    const Module = lazy(loader);

    return (
        <Suspense fallback={<Skeleton className="h-64 w-full" />}>
            <Module {...props} />
        </Suspense>
    );
}
