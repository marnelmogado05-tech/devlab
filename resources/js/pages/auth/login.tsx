import { Form, Head } from '@inertiajs/react';
import AuthStatus from '@/components/auth-status';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

/**
 * Log in.
 *
 * Three things changed from the starter kit's version, all of them defects
 * rather than taste. The status message rendered BELOW the form, so the one
 * sentence explaining why you were sent here ("your password has been reset")
 * arrived under the fold. Every field carried a hand-written `tabIndex`, two of
 * them the same number, which overrides the browser's own order for no gain —
 * the DOM order was already correct. And the errors were rendered next to their
 * inputs without being attached to them, so a screen reader announced a field
 * and a loose sentence with nothing joining the two.
 */
export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Log in" />

            {status && <AuthStatus className="mb-6">{status}</AuthStatus>}

            <PasskeyVerify />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-5">
                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    autoComplete="email"
                                    placeholder="email@example.com"
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
                                <div className="flex items-center gap-3">
                                    <Label htmlFor="password">Password</Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="text-muted-foreground ml-auto text-sm"
                                        >
                                            Forgot it?
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    autoComplete="current-password"
                                    placeholder="Password"
                                    aria-invalid={Boolean(errors.password)}
                                    aria-describedby={
                                        errors.password
                                            ? 'password-error'
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="password-error"
                                    message={errors.password}
                                />
                            </div>

                            {/*
                             * 44×44 is the touch target §45 asks for, and a
                             * checkbox is 16. The label is part of the control,
                             * so the padding goes on the pair rather than on
                             * the box — which would only make a small box sit
                             * in a large empty square.
                             */}
                            <div className="flex items-center gap-3 py-2">
                                <Checkbox id="remember" name="remember" />
                                <Label
                                    htmlFor="remember"
                                    className="text-muted-foreground font-normal"
                                >
                                    Keep me signed in on this device
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Log in
                            </Button>
                        </div>

                        <p className="text-muted-foreground text-center text-sm">
                            No account yet?{' '}
                            <TextLink href={register()}>Make one</TextLink>
                        </p>
                    </>
                )}
            </Form>
        </>
    );
}

Login.layout = {
    title: 'Log in',
    description: 'Pick up wherever you left off.',
    variant: 'guest',
};
