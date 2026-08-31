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

                <div className="grid gap-3">
                    {items.length > 0 ? (
                        items.map((place) => (
                            <article key={place.id} className="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                                <h2 className="mb-2 text-lg">
                                    <Link
                                        href={places.show.url({ place: place.id })}
                                        className="text-neutral-900 no-underline hover:text-neutral-700"
                                    >
                                        {place.name}
                                    </Link>
                                </h2>
                                <p className="m-0 text-neutral-500">
                                    {t('place_index_stats', {
                                        devices: place.devices_count ?? 0,
                                        bookings: place.bookings_count ?? 0,
                                        codes: place.access_codes_count ?? 0,
                                    })}
                                </p>
                            </article>
                        ))
                    ) : (
                        <EmptyState message={t('place_empty_state')} action={newPlaceAction} />
                    )}
                </div>
            </Page>
        </AppLayout>
    );
}
