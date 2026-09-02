import { Head, Link } from '@inertiajs/react';

import { EmptyState } from '@/components/empty-state';
import { FilterBar } from '@/components/filter-bar';
import { Page, PageHeader } from '@/components/page';
import { Pagination } from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import devices from '@/routes/app/devices';
import type { Device, Paginated, PlaceOption } from '@/types';

interface DevicesIndexProps {
    devices: Paginated<Device>;
    places: PlaceOption[];
    search: string;
    placeId: string | null;
    filters: {
        place_id: string | null;
        search: string;
        status: string;
    };
    [key: string]: unknown;
}

export default function DevicesIndex({ devices: paginatedDevices, places, search, placeId, filters }: DevicesIndexProps) {
    const { t } = useTranslations();
    const items = paginatedDevices.data;

    const headerActions = (
        <>
            <Button variant="outline" asChild>
                <Link href={devices.integrations.index.url()}>{t('integrations')}</Link>
            </Button>
            <Button asChild>
                <Link href={devices.create.url()}>{t('new_device')}</Link>
            </Button>
        </>
    );

    return (
        <AppLayout>
            <Head title={t('devices')} />

            <Page>
                <PageHeader title={t('devices')} actions={headerActions} />

                <FilterBar
                    url={devices.index.url()}
                    fields={[
                        {
                            type: 'place',
                            key: 'place_id',
                            label: t('filter_by_place'),
                            places,
                            includeEmpty: true,
                            emptyOptionLabel: t('all_places'),
                            includeUnassigned: true,
                            unassignedOptionLabel: t('unassigned_place'),
                        },
                        {
                            type: 'select',
                            key: 'status',
                            label: t('devices_status_label'),
                            options: [
                                { value: '', label: t('booking_source_option_all') },
                                { value: 'online', label: t('devices_status_online') },
                                { value: 'offline', label: t('devices_status_offline') },
                            ],
                        },
                        { type: 'search', key: 'search', label: t('search_label'), placeholder: t('device_search_placeholder') },
                    ]}
                    values={{ place_id: placeId ?? '', search, status: filters.status }}
                    showClear={false}
                />

                {items.length > 0 ? (
                    <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white">
                        {items.map((device) => {
                            const placeNames = (device.places ?? []).map((place) => place.name).join(', ') || device.place?.name;

                            return (
                                <div
                                    key={device.id}
                                    className="flex flex-wrap items-center gap-x-4 gap-y-1.5 border-b border-neutral-100 px-4.5 py-3.5 last:border-b-0"
                                >
                                    <div className="min-w-[200px] flex-1">
                                        <Link
                                            href={devices.show.url({ device: device.id })}
                                            className="text-[13.5px] font-semibold text-neutral-900 no-underline hover:text-primary-700"
                                        >
                                            {device.name}
                                        </Link>
                                        <StatusBadge variant={device.is_available ? 'success' : 'neutral'} className="ml-2 align-middle">
                                            {device.is_available ? t('online') : t('offline')}
                                        </StatusBadge>
                                        <p className="m-0 mt-0.5 text-[12.5px] text-neutral-500">
                                            {t('device_index_locations', { names: placeNames || t('unassigned_place') })}
                                        </p>
                                    </div>
                                    <p className="m-0 flex-shrink-0 text-[12.5px] text-neutral-500">
                                        {t('brand')}: {device.brand}
                                    </p>
                                    <p className="m-0 flex-shrink-0 text-[12.5px] text-neutral-500">
                                        {t('device_index_functions', { count: device.device_functions_count ?? 0 })}
                                    </p>
                                    <div className="ml-auto flex flex-shrink-0 gap-2">
                                        <Link
                                            href={devices.show.url({ device: device.id })}
                                            className="rounded-md border border-neutral-200 px-3 py-1.5 text-[12.5px] font-semibold text-neutral-700 no-underline hover:bg-neutral-50"
                                        >
                                            {t('details')}
                                        </Link>
                                        <Link
                                            href={devices.control.url({ device: device.id })}
                                            className="rounded-md bg-primary-500 px-3 py-1.5 text-[12.5px] font-semibold text-white no-underline hover:bg-primary-700"
                                        >
                                            {t('control')}
                                        </Link>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                ) : (
                    <EmptyState
                        message={t('device_empty_state')}
                        action={
                            <Button asChild>
                                <Link href={devices.create.url()}>{t('new_device')}</Link>
                            </Button>
                        }
                    />
                )}

                <Pagination paginator={paginatedDevices} />
            </Page>
        </AppLayout>
    );
}
