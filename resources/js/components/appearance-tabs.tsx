import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { HTMLAttributes } from 'react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

export default function AppearanceToggleTab({
    className = '',
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    const { appearance, updateAppearance } = useAppearance();

    const tabs: { value: Appearance; icon: LucideIcon; label: string }[] = [
        { value: 'light', icon: Sun, label: 'Light' },
        { value: 'dark', icon: Moon, label: 'Dark' },
        { value: 'system', icon: Monitor, label: 'System' },
    ];

    return (
        <div
            className={cn(
                'bg-muted inline-flex gap-1 rounded-md p-1',
                className,
            )}
            {...props}
        >
            {tabs.map(({ value, icon: Icon, label }) => (
                <button
                    key={value}
                    onClick={() => updateAppearance(value)}
                    className={cn(
                        /*
                         * Tokens, not hardcoded neutrals. The inactive state
                         * used to hover to `text-black` with no dark-theme
                         * counterpart, so hovering a tab in the dark theme put
                         * black text on a dark grey — about 1.6:1, which is
                         * invisible. Every colour here now comes from the theme
                         * and both directions of hover move TOWARDS the
                         * foreground, never away from it.
                         */
                        'focus-visible:ring-ring flex min-h-9 items-center rounded-sm px-3.5 py-1.5 transition-colors focus-visible:ring-2 focus-visible:outline-none',
                        appearance === value
                            ? 'bg-card text-foreground'
                            : 'text-muted-foreground hover:text-foreground',
                    )}
                >
                    <Icon className="-ml-1 h-4 w-4" />
                    <span className="ml-1.5 text-sm">{label}</span>
                </button>
            ))}
        </div>
    );
}
