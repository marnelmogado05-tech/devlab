import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

/**
 * The theme control, in the rail rather than in settings.
 *
 * Appearance was a settings page, which put a preference every visitor changes
 * on sight three clicks deep behind an account they may not have — and it is
 * not an account preference at all: it lives in `localStorage` and a cookie, so
 * a signed-out visitor has one too. It belongs where it is used.
 *
 * Nothing here transitions. Every other control in the rail eases its colours
 * because the change is a response to the pointer; this one repaints the entire
 * document, and a 150ms fade on several hundred elements at once reads as lag
 * rather than polish. See `[data-theme-switching]` in `app.css` for the other
 * half of that — this control being instant is worth little if the page it
 * changes is not.
 */
const modes: { value: Appearance; icon: LucideIcon; label: string }[] = [
    { value: 'light', icon: Sun, label: 'Light' },
    { value: 'dark', icon: Moon, label: 'Dark' },
    { value: 'system', icon: Monitor, label: 'System' },
];

/**
 * Three icon buttons, one pressed.
 *
 * A toggle group rather than a radiogroup: each button is its own tab stop and
 * announces its own pressed state, which needs no roving-tabindex key handling
 * to be correct. The icons are decorative — the label each button carries is
 * the accessible name, not a tooltip.
 */
export function ThemeSwitcher({ className }: { className?: string }) {
    const { appearance, updateAppearance } = useAppearance();

    return (
        <div
            role="group"
            aria-label="Theme"
            /*
             * 9px outside, 7px inside. Rounder than anything else in the
             * system on purpose — but not a pill, because a pill here means
             * "press me" and only "I'm Bored" gets to say that. The two
             * radii differ by exactly the 2px of padding between them, which
             * is what keeps the curves concentric instead of the inner ones
             * looking pinched inside the outer.
             */
            className={cn(
                'border-border inline-flex items-center gap-0.5 rounded-[9px] border p-0.5',
                className,
            )}
        >
            {modes.map(({ value, icon: Icon, label }) => {
                const active = appearance === value;

                return (
                    <button
                        key={value}
                        type="button"
                        onClick={() => updateAppearance(value)}
                        aria-pressed={active}
                        title={label}
                        className={cn(
                            'focus-visible:ring-ring flex size-8 items-center justify-center rounded-[7px] focus-visible:ring-2 focus-visible:outline-none',
                            /*
                             * Active borrows rule 1 of the rack — a plate is
                             * lighter than the ground — so the pressed button
                             * reads as raised in both themes without a shadow
                             * or a fill colour invented for this one control.
                             */
                            active
                                ? 'bg-card text-foreground'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        <Icon className="size-4" aria-hidden />
                        <span className="sr-only">{label}</span>
                    </button>
                );
            })}
        </div>
    );
}

/**
 * The same control below `md`, collapsed to one button that cycles.
 *
 * Three 32px buttons plus an avatar plus the accent button plus a hamburger do
 * not fit a 375px rail, and the one thing that must never be pushed off is the
 * product's own button. So the switcher shows the mode it is IN and moves to
 * the next on press — the state is still visible, it just costs presses instead
 * of width.
 */
export function ThemeCycleButton({ className }: { className?: string }) {
    const { appearance, updateAppearance } = useAppearance();

    const index = Math.max(
        modes.findIndex((mode) => mode.value === appearance),
        0,
    );
    const current = modes[index];
    const next = modes[(index + 1) % modes.length];
    const Icon = current.icon;

    return (
        <button
            type="button"
            onClick={() => updateAppearance(next.value)}
            aria-label={`Theme: ${current.label}. Switch to ${next.label}.`}
            className={cn(
                'hover:bg-accent focus-visible:ring-ring flex size-11 items-center justify-center rounded-[9px] focus-visible:ring-2 focus-visible:outline-none',
                className,
            )}
        >
            <Icon className="size-5" aria-hidden />
        </button>
    );
}
