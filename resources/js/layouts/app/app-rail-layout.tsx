import { Breadcrumbs } from '@/components/breadcrumbs';
import { Rail } from '@/components/rail';
import type { AppLayoutProps } from '@/types';

/**
 * The application shell: a rail, then the page.
 *
 * No sidebar, no inset, no collapsible chrome — the page gets the full width and
 * one horizontal rule above it. The max width is 1400px rather than unbounded so
 * prose and card grids do not stretch to a 34" monitor, but an experience that
 * needs the room can opt out by rendering its own container.
 *
 * Breadcrumbs render in a thin bar below the rail, and only when a page supplies
 * them. The starter kit reserved a fixed 4rem header for breadcrumbs on every
 * page including the ones with none, which is 4rem of nothing above most of the
 * application.
 */
export default function AppRailLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <div className="bg-background flex min-h-dvh flex-col">
            <Rail />

            {breadcrumbs.length > 0 && (
                <div className="border-border border-b">
                    <div className="mx-auto flex h-10 max-w-[1400px] items-center px-4 sm:px-6">
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    </div>
                </div>
            )}

            <main className="mx-auto w-full max-w-[1400px] min-w-0 flex-1 px-4 py-6 sm:px-6">
                {children}
            </main>
        </div>
    );
}
