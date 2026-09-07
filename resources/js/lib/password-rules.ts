/**
 * The minimum password length, read out of the server's own rules string.
 *
 * Fortify hands every password form `Password::defaults()->toPasswordRulesString()`
 * — the Apple `passwordrules` format, which the inputs already forward to the
 * browser's generator. The same string is the only trustworthy source for what
 * to TELL someone before they type, and it has to be read rather than
 * hard-coded: `Password::defaults()` is 12 characters and an
 * uncompromised check in production and 8 elsewhere, so any number written into
 * the React would be wrong in one environment or the other.
 *
 * Returns null when the string carries no minimum, in which case the caller
 * should say nothing rather than guess.
 */
export function minimumPasswordLength(passwordRules: string): number | null {
    const match = /minlength:\s*(\d+)/i.exec(passwordRules);

    return match ? Number(match[1]) : null;
}
