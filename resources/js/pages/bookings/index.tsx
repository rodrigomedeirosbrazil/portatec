import { Head, Link } from '@inertiajs/react';

import { EmptyState } from '@/components/empty-state';
import { FilterBar } from '@/components/filter-bar';
import { Page, PageHeader } from '@/components/page';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
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
    // `status: 'all'` é o padrão, não um recorte — não conta como filtro ativo.
    const hasActiveFilters = Boolean(
        filters.place_id || filters.date_from || filters.date_to || (filters.status && filters.status !== 'all') || filters.guest || filters.source,
    );

    const headerActions = (
        <>
            <Button variant="outline" asChild>
                <Link href={bookings.integrations.index.url()}>{t('integrations')}</Link>
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
                                // 'all' e não '' porque é o valor que o backend devolve
                                // em `filters.status`; com '' o select não casaria e
                                // apareceria vazio.
                                { value: 'all', label: t('booking_status_option_all') },
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
                    showClear
                    sendEmptyValues
                />

                <p className="m-0 text-sm text-neutral-500">{t('booking_results_count', { total: String(paginatedBookings.meta.total) })}</p>

                {/* Coluna única: a lista é cronológica (em andamento -> futuras ->
                    concluídas) e duas colunas fariam a leitura zigzaguear, escondendo
                    essa ordem. */}
                <div className={cn('overflow-hidden rounded-lg border border-neutral-200 bg-white', items.length === 0 && 'border-none bg-transparent')}>
                    {items.length > 0 ? (
                        items.map((booking) => {
                            const nights = booking.check_in && booking.check_out ? nightsBetween(booking.check_in, booking.check_out) : 0;

                            return (
                                <div
                                    key={booking.id}
                                    className="flex flex-wrap items-center gap-x-4 gap-y-1.5 border-b border-neutral-100 px-4.5 py-3.5 last:border-b-0"
                                >
                                    <div className="min-w-[220px] flex-1">
                                        <Link
                                            href={bookings.show.url({ booking: booking.id })}
                                            className="text-[13.5px] font-semibold text-neutral-900 no-underline hover:text-primary-700"
                                        >
                                            {booking.guest_name || t('booking_no_guest_name')}
                                        </Link>
                                        <span className="ml-2 inline-flex items-center gap-1.5 align-middle">
                                            {booking.status === 'current' ? (
                                                <StatusBadge variant="success">{t('booking_status_badge_current')}</StatusBadge>
                                            ) : null}
                                            {booking.status === 'past' ? (
                                                <StatusBadge variant="neutral">{t('booking_status_badge_past')}</StatusBadge>
                                            ) : null}
                                            {booking.source !== 'manual' ? (
                                                <StatusBadge variant="neutral">{t('booking_ical_badge')}</StatusBadge>
                                            ) : null}
                                        </span>
                                        {!filters.place_id ? <p className="m-0 mt-0.5 text-[12.5px] text-neutral-500">{booking.place?.name}</p> : null}
                                    </div>
                                    <p className="m-0 flex-1 basis-[240px] text-[12.5px] text-neutral-500">
                                        {t('booking_date_range', {
                                            check_in: booking.check_in ? formatDateTime(booking.check_in) : '',
                                            check_out: booking.check_out ? formatDateTime(booking.check_out) : '',
                                        })}{' '}
                                        <span className="text-neutral-400">
                                            ({nights} {nights === 1 ? t('night_singular') : t('night_plural')})
                                        </span>
                                    </p>
                                    <Link
                                        href={bookings.show.url({ booking: booking.id })}
                                        className="ml-auto flex-shrink-0 text-[12.5px] font-semibold text-primary-700 no-underline hover:text-primary-500"
                                    >
                                        {t('details')}
                                    </Link>
                                </div>
                            );
                        })
                    ) : hasActiveFilters ? (
                        <EmptyState
                            message={t('booking_empty_state_filtered')}
                            action={
                                <Button asChild>
                                    <Link href={bookings.index.url()}>{t('clear_filters')}</Link>
                                </Button>
                            }
                        />
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
