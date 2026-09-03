<?php

namespace App\Services\Execution;

/**
 * Makes sandbox output safe to store, log and read.
 *
 * The control for S5. Output is the sandbox's only channel out, so the most
 * likely way a compromised one attacks the rest of the system is by BEING READ —
 * by a browser, a log aggregator, or a maintainer's terminal.
 *
 * Three things happen here, in this order:
 *
 *  1. **Capped.** Enforced while reading via {@see append}, not by truncating a
 *     complete string afterwards. A program printing infinitely fills the
 *     reader's memory long before anyone gets to call `substr` on it — a
 *     truncate-at-the-end implementation is not a control, it is a comment.
 *  2. **Control characters stripped.** ANSI escapes can rewrite a terminal's
 *     display, move the cursor and hide text. A maintainer reading logs is a
 *     reader, and `cat` of a stored result should not be able to lie to them.
 *  3. **Invalid UTF-8 replaced.** Output is bytes; the database column is text.
 *     Storing an invalid sequence throws at the driver, which turns hostile
 *     output into an application error — exactly the outcome the sandbox exists
 *     to prevent.
 *
 * What this class does NOT do is escape HTML. Escaping belongs at the point of
 * rendering, where the target syntax is known; doing it here would double-encode
 * in a JSON payload and leave the value wrong everywhere else.
 */
class OutputSanitiser
{
    /** Marks output that hit the cap, so a reader knows there was more. */
    public const TRUNCATION_NOTICE = "\n[output truncated]";

    private string $buffer = '';

    private bool $truncated = false;

    public function __construct(private readonly int $maxBytes) {}

    public static function fromConfig(): self
    {
        return new self((int) config('devlab.execution.output.max_bytes'));
    }

    /**
     * Add a chunk as it is read, stopping at the cap.
     *
     * Returns whether the reader should keep going. A caller that ignores a
     * `false` is the bug this signature exists to make visible.
     */
    public function append(string $chunk): bool
    {
        if ($this->truncated) {
            return false;
        }

        $remaining = $this->maxBytes - strlen($this->buffer);

        if (strlen($chunk) < $remaining) {
            $this->buffer .= $chunk;

            return true;
        }

        $this->buffer .= substr($chunk, 0, max(0, $remaining));
        $this->truncated = true;

        return false;
    }

    public function truncated(): bool
    {
        return $this->truncated;
    }

    /**
     * The sanitised result.
     */
    public function value(): string
    {
        $value = self::clean($this->buffer);

        return $this->truncated ? $value.self::TRUNCATION_NOTICE : $value;
    }

    /**
     * Strip control characters and repair encoding.
     *
     * Tab, newline and carriage return survive: they are how output is laid out,
     * and removing them would make a stack trace unreadable to defend against
     * nothing. Everything else in C0, plus DEL and the C1 range, goes.
     */
    public static function clean(string $output): string
    {
        // Replace invalid sequences rather than dropping them, so a truncated
        // multi-byte character at the cap boundary cannot corrupt what follows.
        $valid = mb_convert_encoding($output, 'UTF-8', 'UTF-8');

        return (string) preg_replace(
            '/[^\P{C}\t\n\r]/u',
            '',
            $valid,
        );
    }
}
