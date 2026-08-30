import { Head, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';

import { FormField } from '@/components/form-field';
import { Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import places from '@/routes/app/places';

interface PlaceForm {
    name: string;
}

export default function PlacesCreate() {
    const { t } = useTranslations();
    const { data, setData, post, processing, errors } = useForm<PlaceForm>({
        name: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(places.store.url());
    };

    return (
        <AppLayout>
            <Head title={t('create_place_title')} />

            <Page>
                <PageHeader title={t('create_place_title')} backHref={places.index.url()} />

                <form onSubmit={submit} className="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                    <FormField htmlFor="name" label={t('name')} error={errors.name} required>
                        <Input
                            id="name"
                            type="text"
                            required
                            autoFocus
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                    </FormField>

                    <Button type="submit" disabled={processing} className="mt-3">
                        {t('save_place')}
                    </Button>
                </form>
            </Page>
        </AppLayout>
    );
}
