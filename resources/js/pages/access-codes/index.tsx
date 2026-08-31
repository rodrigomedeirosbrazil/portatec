import { Head, Link } from '@inertiajs/react';

import { EmptyState } from '@/components/empty-state';
import { FilterBar } from '@/components/filter-bar';
import { Page, PageHeader } from '@/components/page';
import { Pagination } from '@/components/pagination';
import { StatusBadge, type StatusBadgeVariant } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import accessCodes from '@/routes/app/access-codes';
import type { AccessCode, Paginated, PlaceOption } from '@/types';

interface AccessCodesIndexFilters {
    place_id: number | null;
    status: string;
    search: string;
}

interface AccessCodesIndexProps {
    accessCodes: Paginated<AccessCode>;
    places: PlaceOption[];
    filters: AccessCodesIndexFilters;
    now: string;
    [key: string]: unknown;
}

type CodeStatus = 'active' | 'future' | 'expired';

function formatDateTime(iso: string): string {
    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));
}

function codeStatus(accessCode: AccessCode, now: string): CodeStatus {
    const nowDate = new Date(now);

    if (accessCode.end !== null && new Date(accessCode.end) < nowDate) {
        return 'expired';
    }

    if (accessCode.start !== null && new Date(accessCode.start) > nowDate) {
        return 'future';
    }

    return 'active';
}

const statusVariant: Record<CodeStatus, StatusBadgeVariant> = {
    active: 'success',
    future: 'warning',
    expired: 'neutral',
};

export default function AccessCodesIndex({ accessCodes: paginatedAccessCodes, places, filters, now }: AccessCodesIndexProps) {
    const { t } = useTranslations();
    const items = paginatedAccessCodes.data;

    const headerActions = (
        <Button asChild>
            <Link href={accessCodes.create.url()}>{t('new_access_code')}</Link>
        </Button>
    );

    return (
        <AppLayout>
            <Head title={t('access_codes')} />

            <Page>
                <PageHeader title={t('access_codes')} actions={headerActions} />

                <FilterBar
                    url={accessCodes.index.url()}
                    fields={[
                        {
                            type: 'place',
                            key: 'place_id',
                            label: t('place'),
                            places,
                            includeEmpty: true,
                            emptyOptionLabel: t('all_places'),
                        },
                        {
                            type: 'select',
                            key: 'status',
                            label: t('status'),
                            options: [
                                { value: '', label: t('access_code_status_option_all') },
                                { value: 'active', label: t('access_code_status_option_active') },
                                { value: 'future', label: t('access_code_status_option_future') },
                                { value: 'expired', label: t('access_code_status_option_expired') },
                            ],
                        },
                        {
                            type: 'search',
                            key: 'search',
                            label: t('access_code_search_label'),
                            placeholder: t('access_code_search_placeholder'),
                        },
                    ]}
                    values={{
                        place_id: filters.place_id !== null ? String(filters.place_id) : '',
                        status: filters.status,
                        search: filters.search,
                    }}
                    gridClassName="sm:grid-cols-2 lg:grid-cols-3"
                    showClear={false}
                />

                <div className="grid gap-3">
                    {items.length > 0 ? (
                        items.map((accessCode) => {
                            const status = codeStatus(accessCode, now);

                            return (
                                <article key={accessCode.id} className="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                                    <div className="flex items-start justify-between">
                                        <h2 className="mb-2 text-lg">
                                            <Link
                                                href={accessCodes.edit.url({ accessCode: accessCode.id })}
                                                className="text-neutral-900 no-underline hover:text-neutral-700"
                                            >
                                                {t('access_code_pin_label', { pin: accessCode.pin })}
                                            </Link>
                                        </h2>
                                        <StatusBadge variant={statusVariant[status]}>{t(`access_code_status_${status}`)}</StatusBadge>
                                    </div>
                                    <p className="m-0 text-neutral-500">{accessCode.display_name}</p>
                                    <p className="mt-1 m-0 text-neutral-500">
                                        {t('access_code_date_range', {
                                            start: accessCode.start ? formatDateTime(accessCode.start) : '',
                                            end: accessCode.end ? formatDateTime(accessCode.end) : t('access_code_no_end'),
                                        })}
                                    </p>
                                </article>
                            );
                        })
                    ) : (
                        <EmptyState
                            message={t('access_code_empty_state')}
                            action={
                                <Button asChild>
                                    <Link href={accessCodes.create.url()}>{t('new_access_code')}</Link>
                                </Button>
                            }
                        />
                    )}
                </div>

                <Pagination paginator={paginatedAccessCodes} />
            </Page>
        </AppLayout>
    );
}
