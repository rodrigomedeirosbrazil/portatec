import { Head, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';

import { email as sendResetLink } from '@/routes/password';
import { login } from '@/routes';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { GuestLayout } from '@/layouts/guest-layout';
import { useTranslations } from '@/hooks/use-translations';

interface ForgotPasswordForm {
    email: string;
}

export default function ForgotPassword() {
    const { t } = useTranslations();
    const { data, setData, post, processing, errors } = useForm<ForgotPasswordForm>({
        email: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(sendResetLink.url());
    };

    return (
        <GuestLayout subtitle={t('forgot_password_subtitle')}>
            <Head title={t('forgot_password_subtitle')} />

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

                <Button type="submit" className="w-full" disabled={processing}>
                    {t('forgot_password_send_link')}
                </Button>
            </form>

            <div className="mt-5 text-sm">
                <a href={login.url()} className="text-neutral-700 underline">
                    {t('forgot_password_back_to_login')}
                </a>
            </div>
        </GuestLayout>
    );
}
