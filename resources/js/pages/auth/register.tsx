import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';

import { store } from '@/actions/App/Http/Controllers/Auth/RegisteredUserController';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/hooks/use-translations';
import { GuestLayout } from '@/layouts/guest-layout';
import { login } from '@/routes';

interface RegisterForm {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
}

export default function Register() {
    const { t } = useTranslations();
    const { data, setData, post, processing, errors } = useForm<RegisterForm>({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(store.url());
    };

    return (
        <GuestLayout subtitle={t('register_subtitle')}>
            <Head title={t('register_submit')} />

            <form onSubmit={submit} className="space-y-4">
                <FormField htmlFor="name" label={t('name')} error={errors.name} required>
                    <Input
                        id="name"
                        type="text"
                        required
                        autoFocus
                        autoComplete="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                    />
                </FormField>

                <FormField htmlFor="email" label={t('email')} error={errors.email} required>
                    <Input
                        id="email"
                        type="email"
                        required
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
                        autoComplete="new-password"
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
                        type="password"
                        required
                        autoComplete="new-password"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                    />
                </FormField>

                <Button type="submit" disabled={processing} className="w-full">
                    {t('register_submit')}
                </Button>
            </form>

            <div className="mt-5 text-sm">
                <Link href={login.url()} className="text-neutral-700 underline">
                    {t('already_have_account_link')}
                </Link>
            </div>
        </GuestLayout>
    );
}
