import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { store } from '@/routes/two-factor/login';

/**
 * The second factor.
 *
 * One form with two shapes, and the toggle between them rewrites the heading —
 * a recovery code is not "an authentication code" and telling someone to check
 * their authenticator app while showing a field for a printed code is how
 * people end up locked out of their own account.
 *
 * The recovery branch gained a visible label. It had a placeholder and nothing
 * else, and a placeholder disappears the moment you type into it, which leaves
 * a text field with no name for anyone who looks away mid-entry — and no name
 * at all for a screen reader.
 */
export default function TwoFactorChallenge() {
    const [showRecoveryInput, setShowRecoveryInput] = useState<boolean>(false);
    const [code, setCode] = useState<string>('');

    const authConfigContent = useMemo<{
        title: string;
        description: string;
        toggleText: string;
    }>(() => {
        if (showRecoveryInput) {
            return {
                title: 'Use a recovery code',
                description:
                    'Enter one of the emergency codes you saved when you turned two-factor on. Each one works once.',
                toggleText: 'use an authentication code instead',
            };
        }

        return {
            title: 'Authentication code',
            description:
                'Open your authenticator app and enter the six digits it is showing.',
            toggleText: 'use a recovery code instead',
        };
    }, [showRecoveryInput]);

    setLayoutProps({
        title: authConfigContent.title,
        description: authConfigContent.description,
        variant: 'secure',
    });

    const toggleRecoveryMode = (clearErrors: () => void): void => {
        setShowRecoveryInput(!showRecoveryInput);
        clearErrors();
        setCode('');
    };

    return (
        <>
            <Head title="Two-factor authentication" />

            <Form
                {...store.form()}
                className="flex flex-col gap-6"
                resetOnError
                resetOnSuccess={!showRecoveryInput}
            >
                {({ errors, processing, clearErrors }) => (
                    <>
                        {showRecoveryInput ? (
                            <div className="grid gap-2">
                                <Label htmlFor="recovery_code">
                                    Recovery code
                                </Label>
                                <Input
                                    id="recovery_code"
                                    name="recovery_code"
                                    type="text"
                                    autoComplete="one-time-code"
                                    placeholder="xxxxxxxx-xxxxxxxx"
                                    className="font-mono"
                                    autoFocus
                                    required
                                    aria-invalid={Boolean(errors.recovery_code)}
                                    aria-describedby={
                                        errors.recovery_code
                                            ? 'recovery-code-error'
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="recovery-code-error"
                                    message={errors.recovery_code}
                                />
                            </div>
                        ) : (
                            <div className="flex flex-col items-center gap-3">
                                <InputOTP
                                    name="code"
                                    maxLength={OTP_MAX_LENGTH}
                                    value={code}
                                    onChange={(value) => setCode(value)}
                                    disabled={processing}
                                    pattern={REGEXP_ONLY_DIGITS}
                                    aria-label="Authentication code"
                                    aria-invalid={Boolean(errors.code)}
                                    autoFocus
                                >
                                    <InputOTPGroup>
                                        {Array.from(
                                            { length: OTP_MAX_LENGTH },
                                            (_, index) => (
                                                <InputOTPSlot
                                                    key={index}
                                                    index={index}
                                                />
                                            ),
                                        )}
                                    </InputOTPGroup>
                                </InputOTP>

                                <InputError
                                    message={errors.code}
                                    className="text-center"
                                />
                            </div>
                        )}

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Continue
                        </Button>

                        {/*
                         * A real button, not a styled span. It changes what
                         * this page is asking for, which is an action, and an
                         * action has to be reachable by keyboard and announced
                         * as pressable.
                         */}
                        <p className="text-muted-foreground text-center text-sm">
                            Cannot get to it?{' '}
                            <button
                                type="button"
                                onClick={() => toggleRecoveryMode(clearErrors)}
                                className="text-foreground focus-visible:ring-ring cursor-pointer rounded-sm underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! focus-visible:ring-2 focus-visible:outline-none dark:decoration-neutral-500"
                            >
                                {authConfigContent.toggleText}
                            </button>
                        </p>
                    </>
                )}
            </Form>
        </>
    );
}
