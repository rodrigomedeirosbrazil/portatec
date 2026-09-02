import { Head, Link } from '@inertiajs/react';

import { EmptyState } from '@/components/empty-state';
import { Page, PageHeader } from '@/components/page';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import places from '@/routes/app/places';

interface ControlPlace {
    id: number;
    name: string;
    devices_count: number;
    online_count: number;
}

interface ControlIndexProps {
    places: ControlPlace[];
    [key: string]: unknown;
}

export default function ControlIndex({ places: items }: ControlIndexProps) {
    const { t } = useTranslations();

    return (
        <AppLayout breadcrumbs={[{ label: t('control_index_title') }]}>
            <Head title={t('control_index_title')} />

            <Page>
                <PageHeader title={t('control_index_title')} />

                {items.length > 0 ? (
                    <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white">
                        {items.map((place) => {
                            const hasOffline = place.devices_count > 0 && place.online_count < place.devices_count;

                            return (
                                <Link
                                    key={place.id}
                                    href={places.control.url({ place: place.id })}
                                    className="flex items-center gap-4 border-b border-neutral-100 px-4.5 py-3.5 no-underline last:border-b-0 hover:bg-neutral-50"
                                >
                                    <span
                                        className={cn('h-1.5 w-1.5 flex-shrink-0 rounded-full', hasOffline ? 'bg-error-500' : 'bg-success-500')}
                                        aria-hidden="true"
                                    />
                                    <span className="flex-1 text-[13.5px] font-semibold text-neutral-900">{place.name}</span>
                                    <span className="text-[12.5px] text-neutral-500">
                                        {t('dashboard_place_online_label', {
                                            online: place.online_count,
                                            total: place.devices_count,
                                        })}
                                    </span>
                                </Link>
                            );
                        })}
                    </div>
                ) : (
                    <EmptyState message={t('dashboard_empty_state')} />
                )}
            </Page>
        </AppLayout>
    );
}
