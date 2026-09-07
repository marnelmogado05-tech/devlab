import { describe, expect, it } from 'vitest';
import { minimumPasswordLength } from './password-rules';

describe('the password hint', () => {
    it('reads the minimum out of the rules string the server sent', () => {
        expect(
            minimumPasswordLength(
                'minlength: 12; required: lower; required: upper; required: digit;',
            ),
        ).toBe(12);
    });

    it('reads it whatever the surrounding rules are', () => {
        // `Password::defaults()` is 8 outside production and 12 inside it, and
        // the extra tokens differ with it. The number is the only part this
        // cares about.
        expect(minimumPasswordLength('minlength: 8;')).toBe(8);
    });

    it('says nothing rather than guessing when there is no minimum', () => {
        expect(minimumPasswordLength('allowed: ascii-printable;')).toBeNull();
        expect(minimumPasswordLength('')).toBeNull();
    });
});
