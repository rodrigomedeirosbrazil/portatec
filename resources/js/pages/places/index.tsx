import { Head, Link } from '@inertiajs/react';

import { EmptyState } from '@/components/empty-state';
import { FilterBar } from '@/components/filter-bar';
import { Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import places from '@/routes/app/places';
import type { Place } from '@/types';

interface PlacesIndexProps {
    places: Place[];
    search: string;
    [key: string]: unknown;
}

export default function PlacesIndex({ places: items, search }: PlacesIndexProps) {
    const { t } = useTranslations();

    const newPlaceAction = (
        <Button asChild>
            <Link href={places.create.url()}>{t('new_place')}</Link>
        </Button>
    );

    return (
        <AppLayout>
            <Head title={t('places')} />

            <Page>
                <PageHeader title={t('places')} actions={newPlaceAction} />

                <FilterBar
                    url={places.index.url()}
                    fields={[{ type: 'search', key: 'search', placeholder: t('place_search_placeholder') }]}
                    values={{ search }}
                    showClear={false}
                />

                {items.length > 0 ? (
                    <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white">
                        {items.map((place) => (
                            <div
                                key={place.id}
                                className="flex flex-wrap items-center gap-x-4 gap-y-1.5 border-b border-neutral-100 px-4.5 py-3.5 last:border-b-0"
                            >
                                <span className="flex h-8.5 w-8.5 flex-shrink-0 items-center justify-center rounded-lg bg-primary-100 text-primary-700" aria-hidden="true">
                                    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} strokeLinecap="round" strokeLinejoin="round">
                                        <path d="M12 3 L20 8 L20 20 L4 20 L4 8 Z" />
                                        <path d="M9 20 L9 13 L15 13 L15 20" />
                                    </svg>
                                </span>
                                <Link
                                    href={places.show.url({ place: place.id })}
                                    className="min-w-[180px] flex-1 text-[13.5px] font-semibold text-neutral-900 no-underline hover:text-primary-700"
                                >
                                    {place.name}
                                </Link>
                                <p className="m-0 text-[12.5px] text-neutral-500">
                                    {t('place_index_stats', {
                                        devices: place.devices_count ?? 0,
                                        bookings: place.bookings_count ?? 0,
                                        codes: place.access_codes_count ?? 0,
                                    })}
                                </p>
                                <Link
                                    href={places.show.url({ place: place.id })}
                                    className="ml-auto flex-shrink-0 text-[12.5px] font-semibold text-primary-700 no-underline hover:text-primary-500"
                                >
                                    {t('details')}
                                </Link>
                            </div>
                        ))}
                    </div>
                ) : (
                    <EmptyState message={t('place_empty_state')} action={newPlaceAction} />
                )}
            </Page>
        </AppLayout>
    );
}
