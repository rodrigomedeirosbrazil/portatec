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
    [key: string]: unknown;
}

export default function DevicesIndex({ devices: paginatedDevices, places, search, placeId }: DevicesIndexProps) {
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
                        { type: 'search', key: 'search', label: t('search_label'), placeholder: t('device_search_placeholder') },
                    ]}
                    values={{ place_id: placeId ?? '', search }}
                    showClear={false}
                />

                <div className="grid gap-3">
                    {items.length > 0 ? (
                        items.map((device) => {
                            const placeNames = (device.places ?? []).map((place) => place.name).join(', ') || device.place?.name;

                            return (
                                <article key={device.id} className="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                                    <div className="flex items-start justify-between">
                                        <h2 className="mb-2 text-lg">
                                            <Link
                                                href={devices.show.url({ device: device.id })}
                                                className="text-neutral-900 no-underline hover:text-neutral-700"
                                            >
                                                {device.name}
                                            </Link>
                                        </h2>
                                        <StatusBadge variant={device.is_available ? 'success' : 'neutral'}>
                                            {device.is_available ? t('online') : t('offline')}
                                        </StatusBadge>
                                    </div>
                                    <p className="m-0 text-neutral-500">
                                        {t('device_index_locations', { names: placeNames || t('unassigned_place') })}
                                    </p>
                                    <p className="mt-1 m-0 text-neutral-500">
                                        {t('brand')}: {device.brand}
                                    </p>
                                    <p className="mt-1 m-0 text-neutral-500">
                                        {t('device_index_functions', { count: device.device_functions_count ?? 0 })}
                                    </p>
                                    <div className="mt-2.5 flex gap-2">
                                        <Link
                                            href={devices.show.url({ device: device.id })}
                                            className="text-primary-700 no-underline hover:text-primary-500"
                                        >
                                            {t('details')}
                                        </Link>
                                        <Link
                                            href={devices.control.url({ device: device.id })}
                                            className="text-primary-700 no-underline hover:text-primary-500"
                                        >
                                            {t('control')}
                                        </Link>
                                    </div>
                                </article>
                            );
                        })
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
                </div>

                <Pagination paginator={paginatedDevices} />
            </Page>
        </AppLayout>
    );
}
