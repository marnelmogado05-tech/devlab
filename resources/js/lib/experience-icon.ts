import {
    Bug,
    Container,
    Dices,
    Ghost,
    GitBranch,
    Network,
    Puzzle,
    Siren,
    Swords,
    Terminal,
    type LucideIcon,
} from 'lucide-react';

/**
 * `experiences.icon` holds a lucide icon name, chosen by whoever authored the
 * experience.
 *
 * Named imports rather than the whole library: `import * as icons` would let any
 * name resolve, at the cost of shipping every icon lucide has to every visitor.
 * A handful of kilobytes for a sidebar is not a trade worth making, so the set
 * is explicit and anything unrecognised falls back to a puzzle piece.
 *
 * The names here cover the experiences in §9's roadmap, so an experience
 * authored later usually finds its icon already listed.
 */
const icons: Record<string, LucideIcon> = {
    Bug,
    Container,
    Dices,
    Ghost,
    GitBranch,
    Network,
    Puzzle,
    Siren,
    Swords,
    Terminal,
};

export function experienceIcon(name: string | null): LucideIcon {
    return (name && icons[name]) || Puzzle;
}
