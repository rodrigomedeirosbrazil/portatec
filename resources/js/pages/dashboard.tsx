import { Head, Link } from '@inertiajs/react';

import { EmptyState } from '@/components/empty-state';
import { Page, PageHeader } from '@/components/page';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import bookings from '@/routes/app/bookings';
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

export default function Dashboard({ places: dashboardPlaces, totalDevices, totalOnline, totalOffline, activeBookings, todayCheckIns }: DashboardProps) {
    const { t } = useTranslations();

    return (
        <AppLayout>
            <Head title={t('nav_dashboard')} />

            <Page>
                <PageHeader title={t('nav_dashboard')} />

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div className="rounded-[10px] border border-neutral-300 bg-white p-3.5 text-center">
                        <p className="m-0 text-2xl font-bold text-neutral-900">
                            {totalOnline} / {totalDevices}
                        </p>
                        <p className="m-0 mt-1 text-sm text-neutral-500">{t('dashboard_devices_online_heading')}</p>
                    </div>
                    <div
                        className={cn(
                            'rounded-[10px] border p-3.5 text-center',
                            totalOffline > 0 ? 'border-red-300 bg-red-50' : 'border-neutral-300 bg-white',
                        )}
                    >
                        <p className={cn('m-0 text-2xl font-bold', totalOffline > 0 ? 'text-red-700' : 'text-neutral-900')}>{totalOffline}</p>
                        <p className={cn('m-0 mt-1 text-sm', totalOffline > 0 ? 'text-red-500' : 'text-neutral-500')}>
                            {t('dashboard_devices_offline_heading')}
                        </p>
                    </div>
                    <div className="rounded-[10px] border border-neutral-300 bg-white p-3.5 text-center">
                        <p className="m-0 text-2xl font-bold text-neutral-900">{activeBookings}</p>
                        <p className="m-0 mt-1 text-sm text-neutral-500">{t('dashboard_active_bookings_heading')}</p>
                    </div>
                    <div className="rounded-[10px] border border-neutral-300 bg-white p-3.5 text-center">
                        <p className="m-0 text-2xl font-bold text-neutral-900">{todayCheckIns}</p>
                        <p className="m-0 mt-1 text-sm text-neutral-500">{t('dashboard_today_checkins_heading')}</p>
                    </div>
                </div>

                <div className="grid gap-3">
                    {dashboardPlaces.length > 0 ? (
                        dashboardPlaces.map((place) => {
                            const hasOffline = place.devices_count > 0 && place.online_count < place.devices_count;

                            return (
                                <article
                                    key={place.id}
                                    className={cn('rounded-[10px] border bg-white p-3.5', hasOffline ? 'border-red-300' : 'border-neutral-300')}
                                >
                                    <div className="flex items-start justify-between">
                                        <h2 className="mb-2 text-lg">
                                            <Link href={places.show.url({ place: place.id })} className="text-neutral-900 no-underline hover:text-neutral-700">
                                                {place.name}
                                            </Link>
                                        </h2>
                                        {place.devices_count > 0 ? (
                                            <StatusBadge variant={hasOffline ? 'error' : 'success'}>
                                                {hasOffline ? t('offline') : t('online')}
                                            </StatusBadge>
                                        ) : null}
                                    </div>
                                    <p className="m-0 text-neutral-500">
                                        {t('dashboard_place_online_label', { online: place.online_count, total: place.devices_count })}
                                    </p>
                                    <p className="m-0 mt-1 text-neutral-500">
                                        {t('dashboard_place_next_check_in_label', {
                                            date: place.next_check_in ? formatDateTime(place.next_check_in) : t('dashboard_place_no_upcoming_bookings'),
                                        })}
                                    </p>
                                    <div className="mt-2 flex gap-2">
                                        <Link
                                            href={places.control.url({ place: place.id })}
                                            className="text-sm text-primary-700 no-underline hover:text-primary-500"
                                        >
                                            {t('dashboard_place_control_action')}
                                        </Link>
                                        <Link
                                            href={bookings.index.url({ query: { place_id: place.id } })}
                                            className="text-sm text-primary-700 no-underline hover:text-primary-500"
                                        >
                                            {t('dashboard_place_bookings_action')}
                                        </Link>
                                    </div>
                                </article>
                            );
                        })
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
                </div>
            </Page>
        </AppLayout>
    );
}
