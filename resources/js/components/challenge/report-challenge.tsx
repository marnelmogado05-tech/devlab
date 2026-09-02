import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/challenges/reports';

const reasonLabels: Record<string, string> = {
    wrong_answer: 'The recorded answer is wrong',
    unclear: 'The wording is unclear or missing context',
    broken: 'It does not load, render or evaluate',
    wrong_difficulty: 'The difficulty is mislabelled',
    offensive: 'The content is inappropriate',
    copyright: 'This is not original content',
    security: 'A security concern',
    other: 'Something else',
};

/**
 * "Something's wrong with this challenge."
 *
 * Deliberately understated: a prominent report button invites noise, and the
 * signal worth catching — a wrong answer key — comes from someone who is
 * genuinely certain. It shows nothing about existing reports, because a visible
 * report count spoils the puzzle and exposes the author.
 */
export function ReportChallenge({
    challengeSlug,
    attemptId = null,
    reasons,
    reasonsNeedingDetails,
}: {
    challengeSlug: string;
    attemptId?: number | null;
    reasons: string[];
    reasonsNeedingDetails: string[];
}) {
    const [open, setOpen] = useState(false);
    const [reason, setReason] = useState(reasons[0] ?? 'wrong_answer');

    const detailsRequired = reasonsNeedingDetails.includes(reason);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    className="text-muted-foreground font-mono text-xs"
                >
                    Something&apos;s wrong with this
                </Button>
            </DialogTrigger>

            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Report this challenge</DialogTitle>
                    <DialogDescription>
                        Especially if the recorded answer is wrong — that one
                        quietly corrupts everyone&apos;s score, so it is worth
                        telling us about.
                    </DialogDescription>
                </DialogHeader>

                <Form
                    action={store(challengeSlug)}
                    method="post"
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            {attemptId !== null && (
                                <input
                                    type="hidden"
                                    name="attempt_id"
                                    value={attemptId}
                                />
                            )}

                            <div className="space-y-2">
                                <Label htmlFor="report-reason">
                                    What is wrong?
                                </Label>
                                <select
                                    id="report-reason"
                                    name="reason"
                                    value={reason}
                                    onChange={(event) =>
                                        setReason(event.target.value)
                                    }
                                    className="border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                                >
                                    {reasons.map((value) => (
                                        <option key={value} value={value}>
                                            {reasonLabels[value] ?? value}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="report-details">
                                    Details{' '}
                                    {detailsRequired ? (
                                        <span aria-hidden="true">*</span>
                                    ) : (
                                        <span className="text-muted-foreground font-normal">
                                            (optional)
                                        </span>
                                    )}
                                </Label>
                                <textarea
                                    id="report-details"
                                    name="details"
                                    rows={4}
                                    required={detailsRequired}
                                    aria-describedby={
                                        errors.details
                                            ? 'report-details-error'
                                            : undefined
                                    }
                                    aria-invalid={
                                        errors.details ? true : undefined
                                    }
                                    className="border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:outline-none"
                                    placeholder={
                                        reason === 'wrong_answer'
                                            ? 'What should the answer be, and why?'
                                            : ''
                                    }
                                />
                                {errors.details && (
                                    <p
                                        id="report-details-error"
                                        role="alert"
                                        className="text-destructive text-sm"
                                    >
                                        {errors.details}
                                    </p>
                                )}
                            </div>

                            <Button type="submit" disabled={processing}>
                                {processing ? 'Sending…' : 'Send report'}
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
