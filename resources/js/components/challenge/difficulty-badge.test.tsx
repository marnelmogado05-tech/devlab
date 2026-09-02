import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { DifficultyBadge } from './difficulty-badge';

describe('DifficultyBadge', () => {
    it.each(['easy', 'medium', 'hard', 'expert'])(
        'names the difficulty in text, not only in colour (%s)',
        (difficulty) => {
            /*
             * §44: colour is never the only carrier of state. This is the test
             * that stops the badge being "optimised" into a coloured dot, which
             * would leave it meaningless to a screen reader and to anyone with a
             * colour vision deficiency.
             */
            render(<DifficultyBadge difficulty={difficulty} />);

            expect(screen.getByText(difficulty)).toBeTruthy();
        },
    );

    it('still renders something for a difficulty it does not recognise', () => {
        // Difficulty is content-supplied. An unknown value should read plainly
        // rather than disappear or throw.
        render(<DifficultyBadge difficulty="impossible" />);

        expect(screen.getByText('impossible')).toBeTruthy();
    });
});
