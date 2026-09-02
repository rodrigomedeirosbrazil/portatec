import { Head, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';

import { FormField } from '@/components/form-field';
import { Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import accessCodes from '@/routes/app/access-codes';
import type { AccessCode } from '@/types';

interface AccessCodesEditProps {
    accessCode: AccessCode;
    pin: string;
    start: string;
    end: string | null;
    [key: string]: unknown;
}

interface AccessCodeEditForm {
    pin: string;
    start: string;
    end: string;
}

export default function AccessCodesEdit({ accessCode, pin, start, end }: AccessCodesEditProps) {
    const { t } = useTranslations();
    const { data, setData, put, processing, errors } = useForm<AccessCodeEditForm>({
        pin,
        start,
        end: end ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(accessCodes.update.url({ accessCode: accessCode.id }));
    };

    return (
        <AppLayout
            breadcrumbs={[
                { label: t('nav_access_codes'), href: accessCodes.index.url() },
                { label: t('edit') },
            ]}
        >
            <Head title={t('edit_access_code_title')} />

            <Page>
                <PageHeader title={t('edit_access_code_title')} backHref={accessCodes.index.url()} />

                <form onSubmit={submit} className="grid gap-2.5 rounded-lg border border-neutral-200 bg-white p-3.5">
                    <p className="m-0 mb-2 text-neutral-500">{accessCode.display_name}</p>

                    <FormField htmlFor="pin" label={t('pin')} error={errors.pin}>
                        <Input id="pin" type="text" value={data.pin} onChange={(e) => setData('pin', e.target.value)} />
                    </FormField>

                    <FormField htmlFor="start" label={t('access_code_start_label')} error={errors.start}>
                        <Input id="start" type="datetime-local" value={data.start} onChange={(e) => setData('start', e.target.value)} />
                    </FormField>

                    <FormField htmlFor="end" label={t('access_code_end_label')} error={errors.end}>
                        <Input id="end" type="datetime-local" value={data.end} onChange={(e) => setData('end', e.target.value)} />
                    </FormField>

                    <Button type="submit" disabled={processing} className="justify-self-start">
                        {t('update_access_code')}
                    </Button>
                </form>
            </Page>
        </AppLayout>
    );
}
