export type Difficulty = 'easy' | 'medium' | 'hard' | 'expert';

export interface ExperienceCard {
    slug: string;
    name: string;
    tagline: string | null;
    icon: string | null;
    category: string | null;
    default_difficulty: string;
    estimated_minutes: number;
    challenges_count: number;
}

export interface ExperienceDetail extends ExperienceCard {
    description: string | null;
}

export interface ChallengeSummary {
    slug: string;
    title: string;
    difficulty: string;
    estimated_minutes: number;
    points: number;
    tags: string[];
}

/**
 * A challenge as it is safe to show before it has been solved.
 *
 * There is deliberately no `solution` or `explanation` field here. The server
 * never sends them to an unsolved challenge, and typing them as optional would
 * invite a component to reach for one.
 */
export interface ChallengeDetail {
    slug: string;
    title: string;
    description: string;
    objective: string;
    rules: string | null;
    difficulty: string;
    type: string | null;
    points: number;
    estimated_minutes: number;
    tags: string[];
    version: number;
}

/** Laravel's length-aware paginator, as Inertia serialises it. */
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}
