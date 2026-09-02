import { Head, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';

import { FormField } from '@/components/form-field';
import { Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import places from '@/routes/app/places';
import type { Place } from '@/types';

interface PlaceForm {
    name: string;
}

interface PlacesEditProps {
    place: Place;
    [key: string]: unknown;
}

export default function PlacesEdit({ place }: PlacesEditProps) {
    const { t } = useTranslations();
    const { data, setData, put, processing, errors } = useForm<PlaceForm>({
        name: place.name,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(places.update.url({ place: place.id }));
    };

    return (
        <AppLayout
            breadcrumbs={[
                { label: t('nav_places'), href: places.index.url() },
                { label: place.name, href: places.show.url({ place: place.id }) },
                { label: t('edit') },
            ]}
        >
            <Head title={t('edit_place_title')} />

            <Page>
                <PageHeader title={t('edit_place_title')} backHref={places.show.url({ place: place.id })} />

                <form onSubmit={submit} className="rounded-lg border border-neutral-200 bg-white p-3.5">
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
                        {t('update_place')}
                    </Button>
                </form>
            </Page>
        </AppLayout>
    );
}
