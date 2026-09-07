import { Form, Head } from '@inertiajs/react';
import AuthStatus from '@/components/auth-status';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <>
            <Head title="Verify your email" />

            {status === 'verification-link-sent' && (
                <AuthStatus className="mb-6">
                    A new link is on its way to the address you registered with.
                </AuthStatus>
            )}

            <Form {...send.form()} className="flex flex-col gap-6">
                {({ processing }) => (
                    <>
                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Send it again
                        </Button>

                        {/*
                         * The way out, and deliberately quiet. Logging out is
                         * not the thing to do here — it is the thing to do if
                         * you signed up with the wrong address — so it reads as
                         * a sentence rather than competing with the button.
                         */}
                        <p className="text-muted-foreground text-center text-sm">
                            Wrong address?{' '}
                            <TextLink href={logout()}>
                                Log out and start again
                            </TextLink>
                        </p>
                    </>
                )}
            </Form>
        </>
    );
}

VerifyEmail.layout = {
    title: 'Verify your email',
    description:
        'We sent a link to the address you registered with. Open it and you are done.',
    variant: 'secure',
};
