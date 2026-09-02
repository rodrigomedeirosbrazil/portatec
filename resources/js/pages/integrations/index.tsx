import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import bookings from '@/routes/app/bookings';
import integrationsRoutes from '@/routes/app/bookings/integrations';
import type { Integration, PlaceOption } from '@/types';

interface IntegrationsIndexProps {
    integrations: Integration[];
    places: PlaceOption[];
    placeId: string | null;
    [key: string]: unknown;
}

export default function IntegrationsIndex({ integrations }: IntegrationsIndexProps) {
    const { t } = useTranslations();
    const [integrationToDelete, setIntegrationToDelete] = useState<Integration | null>(null);

    function confirmDelete() {
        if (!integrationToDelete) {
            return;
        }

        router.delete(integrationsRoutes.destroy.url({ integration: integrationToDelete.id }), {
            onFinish: () => setIntegrationToDelete(null),
        });
    }

    return (
        <AppLayout
            breadcrumbs={[
                { label: t('nav_bookings'), href: bookings.index.url() },
                { label: t('integrations') },
            ]}
        >
            <Head title={t('integrations_ical_title')} />

            <Page>
                <PageHeader
                    title={t('integrations_ical_title')}
                    actions={
                        <Button asChild>
                            <Link href={integrationsRoutes.create.url()}>{t('integration_new_action')}</Link>
                        </Button>
                    }
                />

                {integrations.length > 0 ? (
                    <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white">
                        {integrations.map((integration) => {
                            const placeNames = (integration.places ?? []).map((place) => place.name).join(', ');

                            return (
                                <div
                                    key={integration.id}
                                    className="flex flex-wrap items-center gap-x-4 gap-y-1.5 border-b border-neutral-100 px-4.5 py-3.5 last:border-b-0"
                                >
                                    <span className="flex h-8.5 w-8.5 flex-shrink-0 items-center justify-center rounded-lg bg-primary-100 text-primary-700" aria-hidden="true">
                                        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} strokeLinecap="round" strokeLinejoin="round">
                                            <rect x="4" y="5" width="16" height="15" rx="2.5" />
                                            <line x1="4" y1="10" x2="20" y2="10" />
                                            <line x1="8" y1="3" x2="8" y2="7" />
                                            <line x1="16" y1="3" x2="16" y2="7" />
                                        </svg>
                                    </span>
                                    <div className="min-w-[200px] flex-1">
                                        <p className="m-0 text-[13.5px] font-semibold text-neutral-900">{integration.platform?.name ?? t('platform')}</p>
                                        <p className="m-0 mt-0.5 text-[12.5px] text-neutral-500">
                                            {t('integration_places_label', { names: placeNames || t('integration_no_places') })}
                                            {' · '}
                                            {t('integration_last_updated', {
                                                date: integration.updated_at ? new Date(integration.updated_at).toLocaleString('pt-BR') : '',
                                            })}
                                        </p>
                                    </div>
                                    <div className="ml-auto flex flex-shrink-0 gap-2">
                                        <Button asChild variant="outline" size="sm">
                                            <Link href={integrationsRoutes.edit.url({ integration: integration.id })}>
                                                {t('integration_edit_action')}
                                            </Link>
                                        </Button>
                                        <Button variant="destructive" size="sm" onClick={() => setIntegrationToDelete(integration)}>
                                            {t('integration_remove_action')}
                                        </Button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                ) : (
                    <EmptyState
                        message={t('integration_empty_state')}
                        action={
                            <Button asChild>
                                <Link href={integrationsRoutes.create.url()}>{t('integration_new_action')}</Link>
                            </Button>
                        }
                    />
                )}
            </Page>

            <ConfirmDialog
                open={integrationToDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setIntegrationToDelete(null);
                    }
                }}
                title={t('integration_remove_confirm_title')}
                confirmLabel={t('integration_remove_action')}
                onConfirm={confirmDelete}
            />
        </AppLayout>
    );
}
