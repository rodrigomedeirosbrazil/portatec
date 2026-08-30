import { Head, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';

import { store as resetPassword } from '@/routes/password';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { GuestLayout } from '@/layouts/guest-layout';
import { useTranslations } from '@/hooks/use-translations';

interface ResetPasswordProps {
    token: string;
    email: string | null;
    [key: string]: unknown;
}

interface ResetPasswordForm {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
}

export default function ResetPassword({ token, email }: ResetPasswordProps) {
    const { t } = useTranslations();
    const { data, setData, post, processing, errors } = useForm<ResetPasswordForm>({
        token,
        email: email ?? '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(resetPassword.url());
    };

    return (
        <GuestLayout subtitle={t('reset_password_subtitle')}>
            <Head title={t('reset_password_subtitle')} />

            <form onSubmit={submit} className="space-y-4">
                <FormField htmlFor="email" label={t('email')} error={errors.email} required>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        required
                        autoFocus
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                </FormField>

                <FormField htmlFor="password" label={t('new_password')} error={errors.password} required>
                    <Input
                        id="password"
                        name="password"
                        type="password"
                        required
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                </FormField>

                <FormField
                    htmlFor="password_confirmation"
                    label={t('password_confirmation')}
                    error={errors.password_confirmation}
                    required
                >
                    <Input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        required
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                    />
                </FormField>

                <Button type="submit" className="w-full" disabled={processing}>
                    {t('reset_password_submit')}
                </Button>
            </form>
        </GuestLayout>
    );
}
