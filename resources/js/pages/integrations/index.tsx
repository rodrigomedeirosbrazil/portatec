import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { FilterBar } from '@/components/filter-bar';
import { Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import integrationsRoutes from '@/routes/app/bookings/integrations';
import type { Integration, PlaceOption } from '@/types';

interface IntegrationsIndexProps {
    integrations: Integration[];
    places: PlaceOption[];
    placeId: string | null;
    [key: string]: unknown;
}

export default function IntegrationsIndex({ integrations, places, placeId }: IntegrationsIndexProps) {
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
        <AppLayout>
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

                <FilterBar
                    url={integrationsRoutes.index.url()}
                    fields={[
                        {
                            type: 'place',
                            key: 'place_id',
                            label: t('filter_by_place'),
                            places,
                            includeEmpty: true,
                            emptyOptionLabel: t('all_places'),
                        },
                    ]}
                    values={{ place_id: placeId ?? '' }}
                    showClear={false}
                />

                <div className="grid gap-3">
                    {integrations.length > 0 ? (
                        integrations.map((integration) => {
                            const placeNames = (integration.places ?? []).map((place) => place.name).join(', ');

                            return (
                                <article key={integration.id} className="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                                    <div className="mb-2 flex items-center justify-between">
                                        <h2 className="text-lg">{integration.platform?.name ?? t('platform')}</h2>
                                        <div className="flex gap-2">
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
                                    <p className="m-0 text-neutral-500">
                                        {t('integration_places_label', { names: placeNames || t('integration_no_places') })}
                                    </p>
                                    <p className="mt-1 m-0 text-neutral-500">
                                        {t('integration_last_updated', {
                                            date: integration.updated_at ? new Date(integration.updated_at).toLocaleString('pt-BR') : '',
                                        })}
                                    </p>
                                </article>
                            );
                        })
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
                </div>
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
