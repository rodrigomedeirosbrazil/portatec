import { Head, Link } from '@inertiajs/react';

import { EmptyState } from '@/components/empty-state';
import { Page, PageHeader } from '@/components/page';
import { StatTile } from '@/components/stat-tile';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import accessCodes from '@/routes/app/access-codes';
import bookings from '@/routes/app/bookings';
import devicesRoutes from '@/routes/app/devices';
import places from '@/routes/app/places';
import { cn } from '@/lib/utils';

interface DashboardPlace {
    id: number;
    name: string;
    devices_count: number;
    online_count: number;
    next_check_in: string | null;
}

interface DashboardProps {
    places: DashboardPlace[];
    totalDevices: number;
    totalOnline: number;
    totalOffline: number;
    activeBookings: number;
    todayCheckIns: number;
    activeAccessCodes: number;
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

export default function Dashboard({
    places: dashboardPlaces,
    totalDevices,
    totalOnline,
    totalOffline,
    activeBookings,
    todayCheckIns,
    activeAccessCodes,
}: DashboardProps) {
    const { t } = useTranslations();

    const today = new Date().toISOString().slice(0, 10);

    return (
        <AppLayout>
            <Head title={t('nav_dashboard')} />

            <Page>
                <PageHeader title={t('nav_dashboard')} />

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
                    <StatTile
                        label={t('dashboard_devices_online_heading')}
                        value={`${totalOnline}/${totalDevices}`}
                        href={devicesRoutes.index.url({ query: { status: 'online' } })}
                    />
                    <StatTile
                        label={t('dashboard_devices_offline_heading')}
                        value={totalOffline}
                        tone={totalOffline > 0 ? 'error' : 'default'}
                        href={devicesRoutes.index.url({ query: { status: 'offline' } })}
                    />
                    <StatTile
                        label={t('dashboard_active_bookings_heading')}
                        value={activeBookings}
                        href={bookings.index.url({ query: { status: 'current' } })}
                    />
                    <StatTile
                        label={t('dashboard_today_checkins_heading')}
                        value={todayCheckIns}
                        href={bookings.index.url({ query: { date_from: today, date_to: today } })}
                    />
                    <StatTile
                        label={t('dashboard_active_codes_heading')}
                        value={activeAccessCodes}
                        href={accessCodes.index.url({ query: { status: 'active' } })}
                    />
                </div>

                {dashboardPlaces.length > 0 ? (
                    <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white">
                        {dashboardPlaces.map((place) => {
                            const hasOffline = place.devices_count > 0 && place.online_count < place.devices_count;

                            return (
                                <div
                                    key={place.id}
                                    className="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-neutral-100 px-4.5 py-3.5 last:border-b-0"
                                >
                                    <span className={cn('h-1.5 w-1.5 flex-shrink-0 rounded-full', hasOffline ? 'bg-error-500' : 'bg-success-500')} aria-hidden="true" />

                                    <div className="min-w-[200px] flex-1">
                                        <Link
                                            href={places.show.url({ place: place.id })}
                                            className="text-[13.5px] font-semibold text-neutral-900 no-underline hover:text-primary-700"
                                        >
                                            {place.name}
                                        </Link>
                                        {place.devices_count > 0 ? (
                                            <StatusBadge variant={hasOffline ? 'error' : 'success'} className="ml-2 align-middle">
                                                {hasOffline ? t('offline') : t('online')}
                                            </StatusBadge>
                                        ) : null}
                                    </div>

                                    <p className="m-0 flex-1 basis-[200px] text-[12.5px] text-neutral-500">
                                        {t('dashboard_place_online_label', { online: place.online_count, total: place.devices_count })}
                                    </p>
                                    <p className="m-0 flex-1 basis-[200px] text-[12.5px] text-neutral-500">
                                        {t('dashboard_place_next_check_in_label', {
                                            date: place.next_check_in ? formatDateTime(place.next_check_in) : t('dashboard_place_no_upcoming_bookings'),
                                        })}
                                    </p>

                                    <div className="ml-auto flex flex-shrink-0 gap-2">
                                        <Link
                                            href={places.control.url({ place: place.id })}
                                            className="rounded-md border border-neutral-200 px-3 py-1.5 text-[12.5px] font-semibold text-neutral-700 no-underline hover:bg-neutral-50"
                                        >
                                            {t('dashboard_place_control_action')}
                                        </Link>
                                        <Link
                                            href={bookings.index.url({ query: { place_id: place.id } })}
                                            className="rounded-md bg-primary-500 px-3 py-1.5 text-[12.5px] font-semibold text-white no-underline hover:bg-primary-700"
                                        >
                                            {t('dashboard_place_bookings_action')}
                                        </Link>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                ) : (
                    <EmptyState
                        message={t('dashboard_empty_state')}
                        action={
                            <Button asChild size="sm">
                                <Link href={places.create.url()}>{t('new_place')}</Link>
                            </Button>
                        }
                    />
                )}
            </Page>
        </AppLayout>
    );
}
