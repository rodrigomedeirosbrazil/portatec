import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import { Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import bookings from '@/routes/app/bookings';
import places from '@/routes/app/places';
import type { Booking } from '@/types';

interface BookingsShowProps {
    booking: Booking;
    canDelete: boolean;
    [key: string]: unknown;
}

function formatDateTime(iso: string): string {
    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));
}

function nightsBetween(checkIn: string, checkOut: string): number {
    const start = new Date(checkIn);
    const end = new Date(checkOut);
    start.setHours(0, 0, 0, 0);
    end.setHours(0, 0, 0, 0);

    return Math.round((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24));
}

export default function BookingsShow({ booking, canDelete }: BookingsShowProps) {
    const { t } = useTranslations();
    const [confirmingDelete, setConfirmingDelete] = useState(false);

    const nights = booking.check_in && booking.check_out ? nightsBetween(booking.check_in, booking.check_out) : 0;

    function confirmDelete() {
        router.delete(bookings.destroy.url({ booking: booking.id }), {
            onFinish: () => setConfirmingDelete(false),
        });
    }

    const actions = canDelete ? (
        <Button variant="destructive" onClick={() => setConfirmingDelete(true)}>
            {t('booking_remove_action')}
        </Button>
    ) : null;

    return (
        <AppLayout>
            <Head title={t('booking_show_title')} />

            <Page>
                <PageHeader title={t('booking_show_title')} backHref={bookings.index.url()} actions={actions} />

                <div className="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                    <p className="mb-2">
                        <strong>{t('place')}:</strong>{' '}
                        <Link
                            href={places.show.url({ place: booking.place_id })}
                            className="text-primary-500 no-underline hover:text-primary-700"
                        >
                            {booking.place?.name ?? t('booking_show_no_place')}
                        </Link>
                    </p>
                    <p className="mb-2">
                        <strong>{t('guest')}:</strong> {booking.guest_name || t('booking_no_guest_name')}
                    </p>
                    <p className="mb-2">
                        <strong>{t('check_in')}:</strong> {booking.check_in ? formatDateTime(booking.check_in) : ''}
                    </p>
                    <p className="mb-2">
                        <strong>{t('check_out')}:</strong> {booking.check_out ? formatDateTime(booking.check_out) : ''}
                    </p>
                    <p className="mb-2">
                        <strong>{t('booking_show_duration_label')}:</strong> {nights}{' '}
                        {nights === 1 ? t('night_singular') : t('night_plural')}
                    </p>
                    <p className="m-0">
                        <strong>{t('booking_show_pin_label')}:</strong> {booking.access_code?.pin ?? t('booking_show_pin_not_generated')}
                    </p>
                    {!canDelete ? <p className="mt-2 text-sm text-neutral-500">{t('booking_show_ical_notice')}</p> : null}
                </div>
            </Page>

            <ConfirmDialog
                open={confirmingDelete}
                onOpenChange={setConfirmingDelete}
                title={t('booking_remove_confirm_title')}
                confirmLabel={t('booking_remove_action')}
                onConfirm={confirmDelete}
            />
        </AppLayout>
    );
}
