import { Head, Link } from '@inertiajs/react';

import { EmptyState } from '@/components/empty-state';
import { FilterBar } from '@/components/filter-bar';
import { Page, PageHeader } from '@/components/page';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import bookings from '@/routes/app/bookings';
import type { Booking, Paginated, PlaceOption } from '@/types';

interface BookingsIndexFilters {
    place_id: number | null;
    date_from: string | null;
    date_to: string | null;
    status: string;
    guest: string;
    source: string;
}

interface BookingsIndexProps {
    bookings: Paginated<Booking>;
    places: PlaceOption[];
    filters: BookingsIndexFilters;
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

export default function BookingsIndex({ bookings: paginatedBookings, places, filters }: BookingsIndexProps) {
    const { t } = useTranslations();
    const items = paginatedBookings.data;

    const headerActions = (
        <>
            <Button variant="outline" asChild>
                <Link href={bookings.integrations.index.url()}>{t('nav_bookings_integrations')}</Link>
            </Button>
            <Button asChild>
                <Link href={bookings.create.url()}>{t('new_booking')}</Link>
            </Button>
        </>
    );

    return (
        <AppLayout>
            <Head title={t('bookings')} />

            <Page>
                <PageHeader title={t('bookings')} actions={headerActions} />

                <FilterBar
                    url={bookings.index.url()}
                    fields={[
                        {
                            type: 'place',
                            key: 'place_id',
                            label: t('place'),
                            places,
                            includeEmpty: true,
                            emptyOptionLabel: t('all_places'),
                        },
                        { type: 'date', key: 'date_from', label: t('date_from_label') },
                        { type: 'date', key: 'date_to', label: t('date_to_label') },
                        {
                            type: 'select',
                            key: 'status',
                            label: t('status'),
                            options: [
                                { value: '', label: t('booking_status_option_all') },
                                { value: 'future', label: t('booking_status_option_future') },
                                { value: 'current', label: t('booking_status_option_current') },
                                { value: 'past', label: t('booking_status_option_past') },
                            ],
                        },
                        { type: 'search', key: 'guest', label: t('guest'), placeholder: t('booking_guest_search_placeholder') },
                        {
                            type: 'select',
                            key: 'source',
                            label: t('source_label'),
                            options: [
                                { value: '', label: t('booking_source_option_all') },
                                { value: 'manual', label: t('booking_source_option_manual') },
                                { value: 'ical', label: t('booking_source_option_ical') },
                            ],
                        },
                    ]}
                    values={{
                        place_id: filters.place_id !== null ? String(filters.place_id) : '',
                        date_from: filters.date_from ?? '',
                        date_to: filters.date_to ?? '',
                        status: filters.status,
                        guest: filters.guest,
                        source: filters.source,
                    }}
                    gridClassName="sm:grid-cols-2 lg:grid-cols-3"
                    showClear={false}
                />

                <div className="grid gap-3 md:grid-cols-2">
                    {items.length > 0 ? (
                        items.map((booking) => {
                            const nights = booking.check_in && booking.check_out ? nightsBetween(booking.check_in, booking.check_out) : 0;

                            return (
                                <article key={booking.id} className="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                                    <div className="flex items-start justify-between">
                                        <h2 className="mb-2 text-lg">
                                            <Link
                                                href={bookings.show.url({ booking: booking.id })}
                                                className="text-neutral-900 no-underline hover:text-neutral-700"
                                            >
                                                {booking.guest_name || t('booking_no_guest_name')}
                                            </Link>
                                        </h2>
                                        {booking.source !== 'manual' ? (
                                            <StatusBadge variant="neutral">{t('booking_ical_badge')}</StatusBadge>
                                        ) : null}
                                    </div>
                                    {!filters.place_id ? (
                                        <p className="mt-0 mb-1 text-sm font-medium text-neutral-700">{booking.place?.name}</p>
                                    ) : null}
                                    <p className="m-0 text-neutral-500">
                                        {t('booking_date_range', {
                                            check_in: booking.check_in ? formatDateTime(booking.check_in) : '',
                                            check_out: booking.check_out ? formatDateTime(booking.check_out) : '',
                                        })}{' '}
                                        <span className="text-neutral-400">
                                            ({nights} {nights === 1 ? t('night_singular') : t('night_plural')})
                                        </span>
                                    </p>
                                </article>
                            );
                        })
                    ) : (
                        <EmptyState
                            message={t('booking_empty_state')}
                            action={
                                <Button asChild>
                                    <Link href={bookings.create.url()}>{t('new_booking')}</Link>
                                </Button>
                            }
                        />
                    )}
                </div>

                <Pagination paginator={paginatedBookings} />
            </Page>
        </AppLayout>
    );
}
