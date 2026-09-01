import { Head, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';

import { FormField } from '@/components/form-field';
import { Page, PageHeader } from '@/components/page';
import { PlaceSelect } from '@/components/place-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import bookings from '@/routes/app/bookings';
import type { PlaceOption } from '@/types';

interface BookingsCreateProps {
    places: PlaceOption[];
    placeId: number | null;
    [key: string]: unknown;
}

interface BookingCreateForm {
    placeId: string;
    guestName: string;
    checkIn: string;
    checkOut: string;
}

export default function BookingsCreate({ places, placeId }: BookingsCreateProps) {
    const { t } = useTranslations();
    const { data, setData, post, processing, errors } = useForm<BookingCreateForm>({
        placeId: placeId !== null ? String(placeId) : '',
        guestName: '',
        checkIn: '',
        checkOut: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(bookings.store.url());
    };

    return (
        <AppLayout>
            <Head title={t('new_booking')} />

            <Page>
                <PageHeader title={t('new_booking')} backHref={bookings.index.url()} />

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

                    <FormField htmlFor="guestName" label={t('guest')} error={errors.guestName}>
                        <Input
                            id="guestName"
                            type="text"
                            value={data.guestName}
                            onChange={(e) => setData('guestName', e.target.value)}
                        />
                    </FormField>

                    <FormField htmlFor="checkIn" label={t('check_in')} error={errors.checkIn}>
                        <Input
                            id="checkIn"
                            type="datetime-local"
                            value={data.checkIn}
                            onChange={(e) => setData('checkIn', e.target.value)}
                        />
                    </FormField>

                    <FormField htmlFor="checkOut" label={t('check_out')} error={errors.checkOut}>
                        <Input
                            id="checkOut"
                            type="datetime-local"
                            value={data.checkOut}
                            onChange={(e) => setData('checkOut', e.target.value)}
                        />
                    </FormField>

                    <Button type="submit" disabled={processing} className="justify-self-start">
                        {t('save_booking')}
                    </Button>
                </form>
            </Page>
        </AppLayout>
    );
}
