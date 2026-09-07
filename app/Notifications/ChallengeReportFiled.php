<?php

namespace App\Notifications;

use App\Models\ChallengeReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Someone reported a challenge."
 *
 * ADR 0003 pulled reporting into the MVP because a wrong answer key is silent —
 * it corrupts every score derived from it and nothing else in the system
 * notices. That argument only holds if a maintainer FINDS OUT. Until this
 * existed, filing a report wrote a row and told nobody: the signal arrived and
 * then waited for someone to remember `devlab:reports`, which on a public
 * deployment could be weeks. A `security` report waiting weeks is the version of
 * that which actually hurts.
 *
 * Queued, so a slow or broken mail transport cannot make reporting slow or
 * broken. That does mean the queue worker has to be running — see
 * docs/deployment/README.md, which already requires it.
 *
 * There is no maintainer account to notify (DevLab has no roles by design), so
 * this goes to a configured address via an on-demand notification rather than to
 * a User.
 */
class ChallengeReportFiled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ChallengeReport $report) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $report = $this->report->loadMissing('challenge');
        $challenge = $report->challenge;

        /*
         * Two reasons outrank the rest and say so in the subject line, because
         * the subject is the only part read on a phone at the wrong hour. A
         * wrong key corrupts scores; a security report should not queue behind
         * a wording complaint.
         */
        $urgent = in_array($report->reason, [
            ChallengeReport::REASON_WRONG_ANSWER,
            ChallengeReport::REASON_SECURITY,
        ], true);

        $mail = (new MailMessage)
            ->subject(sprintf(
                '%sChallenge report: %s on %s',
                $urgent ? '[urgent] ' : '',
                $report->reason,
                $challenge->slug,
            ))
            ->line(sprintf('Reason: %s', $report->reason))
            ->line(sprintf('Challenge: %s', $challenge->slug));

        /*
         * The version played against the version live now. A mismatch means the
         * content already moved on and the report may describe something that is
         * already fixed — the same thing `devlab:reports` prints, for the same
         * reason.
         */
        $mail->line($challenge->version === $report->challenge_version
            ? sprintf('Version: %d', $report->challenge_version)
            : sprintf('Version: %d (now %d)', $report->challenge_version, $challenge->version));

        if (filled($report->details)) {
            $mail->line('Details: '.$report->details);
        }

        return $mail
            ->line(sprintf('Triage with: php artisan devlab:reports'))
            ->line(sprintf(
                'Then: php artisan devlab:reports:resolve %d  (or :dismiss)',
                $report->id,
            ));
    }
}
