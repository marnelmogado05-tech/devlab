import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { minimumPasswordLength } from '@/lib/password-rules';
import { update } from '@/routes/password';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
};

/**
 * Set a new password from an emailed link.
 *
 * The address is fixed by the token in the link, so its field is `readOnly` and
 * now looks it — the starter kit rendered a read-only input identically to an
 * editable one, which invites people to try typing in it and wonder why nothing
 * happens. `bg-muted` plus `cursor-not-allowed` says the same thing the
 * attribute already says, visibly.
 */
export default function ResetPassword({ token, email, passwordRules }: Props) {
    const minimum = minimumPasswordLength(passwordRules);

    return (
        <>
            <Head title="Reset password" />

            <Form
                {...update.form()}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Email address</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                autoComplete="email"
                                value={email}
                                readOnly
                                className="bg-muted text-muted-foreground cursor-not-allowed"
                                aria-invalid={Boolean(errors.email)}
                                aria-describedby={
                                    errors.email ? 'email-error' : undefined
                                }
                            />
                            <InputError
                                id="email-error"
                                message={errors.email}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">New password</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="new-password"
                                autoFocus
                                placeholder="New password"
                                passwordrules={passwordRules}
                                aria-invalid={Boolean(errors.password)}
                                aria-describedby={
                                    errors.password
                                        ? 'password-error'
                                        : minimum
                                          ? 'password-hint'
                                          : undefined
                                }
                            />
                            {errors.password ? (
                                <InputError
                                    id="password-error"
                                    message={errors.password}
                                />
                            ) : (
                                minimum && (
                                    <p
                                        id="password-hint"
                                        className="text-muted-foreground text-xs"
                                    >
                                        At least {minimum} characters.
                                    </p>
                                )
                            )}
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Confirm new password
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                placeholder="The same password again"
                                passwordrules={passwordRules}
                                aria-invalid={Boolean(
                                    errors.password_confirmation,
                                )}
                                aria-describedby={
                                    errors.password_confirmation
                                        ? 'password-confirmation-error'
                                        : undefined
                                }
                            />
                            <InputError
                                id="password-confirmation-error"
                                message={errors.password_confirmation}
                            />
                        </div>

                        <Button
                            type="submit"
                            className="mt-1 w-full"
                            disabled={processing}
                            data-test="reset-password-button"
                        >
                            {processing && <Spinner />}
                            Set new password
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}

ResetPassword.layout = {
    title: 'Set a new password',
    description: 'This link works once. Choose something you will remember.',
    variant: 'guest',
};
