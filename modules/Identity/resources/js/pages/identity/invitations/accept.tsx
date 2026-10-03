import { Form, Head } from '@inertiajs/react';
import AcceptInvitationController from '@/actions/Modules/Identity/Http/Controllers/AcceptInvitationController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login } from '@/routes';

type Props = {
    token: string;
    invalid: boolean;
    email: string | null;
    name: string | null;
    passwordRules: string;
};

export default function AcceptInvitation({
    token,
    invalid,
    email,
    name,
    passwordRules,
}: Props) {
    if (invalid) {
        return (
            <>
                <Head title="Invitation unavailable" />
                <div className="space-y-4 text-center text-sm text-muted-foreground">
                    <p>
                        This invitation link is invalid or has expired. Ask an
                        administrator to send a new one.
                    </p>
                    <Button asChild variant="outline">
                        <a href={login().url}>Go to login</a>
                    </Button>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Accept invitation" />
            <Form
                {...AcceptInvitationController.store.form(token)}
                resetOnError={['password', 'password_confirmation']}
                className="flex flex-col gap-6"
            >
                {({ errors, processing }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                value={email ?? ''}
                                readOnly
                                disabled
                            />
                            {name && (
                                <p className="text-xs text-muted-foreground">
                                    Welcome, {name}.
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password">Password</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoFocus
                                autoComplete="new-password"
                                placeholder="Password"
                                passwordrules={passwordRules}
                            />
                            <InputError message={errors.password} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Confirm password
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                autoComplete="new-password"
                                placeholder="Confirm password"
                                passwordrules={passwordRules}
                            />
                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>
                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            Set password and continue
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

AcceptInvitation.layout = {
    title: 'Accept your invitation',
    description: 'Choose a password to activate your account',
};
