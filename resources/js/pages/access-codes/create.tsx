import { Head, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';

import { FormField } from '@/components/form-field';
import { Page, PageHeader } from '@/components/page';
import { PlaceSelect } from '@/components/place-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import accessCodes from '@/routes/app/access-codes';
import type { PlaceOption } from '@/types';

interface AccessCodesCreateProps {
    places: PlaceOption[];
    placeId: number | null;
    [key: string]: unknown;
}

interface AccessCodeCreateForm {
    placeId: string;
    pin: string;
    start: string;
    end: string;
}

export default function AccessCodesCreate({ places, placeId }: AccessCodesCreateProps) {
    const { t } = useTranslations();
    const { data, setData, post, processing, errors } = useForm<AccessCodeCreateForm>({
        placeId: placeId !== null ? String(placeId) : '',
        pin: '',
        start: '',
        end: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(accessCodes.store.url());
    };

    return (
        <AppLayout>
            <Head title={t('new_access_code_title')} />

            <Page>
                <PageHeader title={t('new_access_code_title')} backHref={accessCodes.index.url()} />

                <form onSubmit={submit} className="grid gap-2.5 rounded-lg border border-neutral-200 bg-white p-3.5">
                    <PlaceSelect
                        id="placeId"
                        label={t('place')}
                        required
                        value={data.placeId}
                        onChange={(value) => setData('placeId', value)}
                        places={places}
                        error={errors.placeId}
                    />

                    <FormField htmlFor="pin" label={t('access_code_pin_optional_label')} error={errors.pin}>
                        <Input id="pin" type="text" value={data.pin} onChange={(e) => setData('pin', e.target.value)} />
                    </FormField>

                    <FormField htmlFor="start" label={t('access_code_start_label')} error={errors.start}>
                        <Input id="start" type="datetime-local" value={data.start} onChange={(e) => setData('start', e.target.value)} />
                    </FormField>

                    <FormField htmlFor="end" label={t('access_code_end_optional_label')} error={errors.end}>
                        <Input id="end" type="datetime-local" value={data.end} onChange={(e) => setData('end', e.target.value)} />
                    </FormField>

                    <Button type="submit" disabled={processing} className="justify-self-start">
                        {t('save_access_code')}
                    </Button>
                </form>
            </Page>
        </AppLayout>
    );
}
