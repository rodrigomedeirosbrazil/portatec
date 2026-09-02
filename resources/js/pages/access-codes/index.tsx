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
import bookingsRoutes from '@/routes/app/bookings';
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

export default function AccessCodesIndex({ accessCodes: paginatedAccessCodes, filters, now }: AccessCodesIndexProps) {
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
                        status: filters.status,
                        search: filters.search,
                    }}
                    gridClassName="sm:grid-cols-2 lg:grid-cols-3"
                    showClear
                />

                {items.length > 0 ? (
                    <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white">
                        {items.map((accessCode) => {
                            const status = codeStatus(accessCode, now);

                            return (
                                <div
                                    key={accessCode.id}
                                    className="flex flex-wrap items-center gap-x-4 gap-y-1.5 border-b border-neutral-100 px-4.5 py-3.5 last:border-b-0"
                                >
                                    <div className="min-w-[200px] flex-1">
                                        <Link
                                            href={accessCodes.edit.url({ accessCode: accessCode.id })}
                                            className="font-mono text-[14.5px] font-bold tracking-wide text-neutral-900 no-underline hover:text-primary-700"
                                        >
                                            {t('access_code_pin_label', { pin: accessCode.pin })}
                                        </Link>
                                        <StatusBadge variant={statusVariant[status]} className="ml-2 align-middle">
                                            {t(`access_code_status_${status}`)}
                                        </StatusBadge>
                                        {accessCode.booking_id ? (
                                            <Link
                                                href={bookingsRoutes.show.url({ booking: accessCode.booking_id })}
                                                className="m-0 mt-0.5 block text-[12.5px] text-primary-700 no-underline hover:text-primary-500"
                                            >
                                                {accessCode.display_name}
                                            </Link>
                                        ) : (
                                            <p className="m-0 mt-0.5 text-[12.5px] text-neutral-500">{accessCode.display_name}</p>
                                        )}
                                        {!filters.place_id ? (
                                            <p className="m-0 text-[12.5px] text-neutral-400">{accessCode.place?.name}</p>
                                        ) : null}
                                    </div>
                                    <p className="m-0 flex-1 basis-[240px] text-[12.5px] text-neutral-500">
                                        {t('access_code_date_range', {
                                            start: accessCode.start ? formatDateTime(accessCode.start) : '',
                                            end: accessCode.end ? formatDateTime(accessCode.end) : t('access_code_no_end'),
                                        })}
                                    </p>
                                    <Link
                                        href={accessCodes.edit.url({ accessCode: accessCode.id })}
                                        className="ml-auto flex-shrink-0 text-[12.5px] font-semibold text-primary-700 no-underline hover:text-primary-500"
                                    >
                                        {t('edit')}
                                    </Link>
                                </div>
                            );
                        })}
                    </div>
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

                <Pagination paginator={paginatedAccessCodes} />
            </Page>
        </AppLayout>
    );
}
