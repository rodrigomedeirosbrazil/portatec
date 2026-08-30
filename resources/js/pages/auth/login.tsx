import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';

import { store } from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/hooks/use-translations';
import { GuestLayout } from '@/layouts/guest-layout';
import { request as passwordRequest } from '@/routes/password';
import { register } from '@/routes';

interface LoginForm {
    email: string;
    password: string;
    remember: boolean;
}

export default function Login() {
    const { t } = useTranslations();
    const { data, setData, post, processing, errors } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(store.url());
    };

    return (
        <GuestLayout subtitle={t('login_subtitle')}>
            <Head title={t('login_submit')} />

            <form onSubmit={submit} className="space-y-4">
                <FormField htmlFor="email" label={t('email')} error={errors.email} required>
                    <Input
                        id="email"
                        type="email"
                        required
                        autoFocus
                        autoComplete="username"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                </FormField>

                <FormField htmlFor="password" label={t('password')} error={errors.password} required>
                    <Input
                        id="password"
                        type="password"
                        required
                        autoComplete="current-password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                    />
                </FormField>

                <label className="flex items-center gap-2 text-sm text-neutral-700">
                    <Checkbox
                        checked={data.remember}
                        onCheckedChange={(checked) => setData('remember', checked === true)}
                    />
                    {t('remember_me')}
                </label>

                <Button type="submit" disabled={processing} className="w-full">
                    {t('login_submit')}
                </Button>
            </form>

            <div className="mt-5 flex items-center justify-between text-sm">
                <Link href={passwordRequest.url()} className="text-neutral-700 underline">
                    {t('forgot_password_link')}
                </Link>
                <Link href={register.url()} className="text-neutral-700 underline">
                    {t('create_account_link')}
                </Link>
            </div>
        </GuestLayout>
    );
}
