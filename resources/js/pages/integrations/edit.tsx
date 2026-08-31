import { Head, router, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';
import { useState } from 'react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import { FormField } from '@/components/form-field';
import { Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import integrationsRoutes from '@/routes/app/bookings/integrations';
import type { Integration, IntegrationPlace } from '@/types';

interface IntegrationsEditProps {
    integration: Integration;
    [key: string]: unknown;
}

export default function IntegrationsEdit({ integration }: IntegrationsEditProps) {
    const { t } = useTranslations();
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [placeToRemove, setPlaceToRemove] = useState<IntegrationPlace | null>(null);

    const places = integration.places ?? [];

    function confirmDeleteIntegration() {
        router.delete(integrationsRoutes.destroy.url({ integration: integration.id }), {
            onFinish: () => setShowDeleteConfirm(false),
        });
    }

    function confirmRemovePlace() {
        if (!placeToRemove) {
            return;
        }

        router.delete(integrationsRoutes.places.destroy.url({ integration: integration.id, place: placeToRemove.id }), {
            onFinish: () => setPlaceToRemove(null),
        });
    }

    return (
        <AppLayout>
            <Head title={t('integration_edit_title')} />

            <Page>
                <PageHeader title={t('integration_edit_title')} backHref={integrationsRoutes.index.url()} />

                <div className="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                    <p className="m-0 text-neutral-500">
                        {t('platform')}: <strong className="text-neutral-700">{integration.platform?.name ?? t('platform')}</strong>
                    </p>
                    <p className="mt-1 text-sm text-neutral-500">{t('integration_ical_helper_text')}</p>
                </div>

                <div className="grid gap-3">
                    {places.length > 0 ? (
                        places.map((place) => (
                            <IntegrationPlaceRow
                                key={place.id}
                                integrationId={integration.id}
                                place={place}
                                onRemove={() => setPlaceToRemove(place)}
                            />
                        ))
                    ) : (
                        <p className="text-neutral-500">{t('integration_no_places_associated')}</p>
                    )}
                </div>

                <div>
                    <Button type="button" variant="destructive" onClick={() => setShowDeleteConfirm(true)}>
                        {t('integration_delete_action')}
                    </Button>
                </div>
            </Page>

            <ConfirmDialog
                open={placeToRemove !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPlaceToRemove(null);
                    }
                }}
                title={t('integration_remove_place_confirm_title')}
                confirmLabel={t('integration_remove_place_action')}
                onConfirm={confirmRemovePlace}
            />

            <ConfirmDialog
                open={showDeleteConfirm}
                onOpenChange={setShowDeleteConfirm}
                title={t('integration_remove_confirm_title')}
                confirmLabel={t('integration_delete_action')}
                onConfirm={confirmDeleteIntegration}
            />
        </AppLayout>
    );
}

interface IntegrationPlaceRowProps {
    integrationId: number;
    place: IntegrationPlace;
    onRemove: () => void;
}

function IntegrationPlaceRow({ integrationId, place, onRemove }: IntegrationPlaceRowProps) {
    const { t } = useTranslations();
    const { data, setData, put, processing, errors } = useForm({
        externalId: place.external_id,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(integrationsRoutes.places.update.url({ integration: integrationId, place: place.id }));
    };

    return (
        <form onSubmit={submit} className="grid gap-3 rounded-[10px] border border-neutral-300 bg-white p-3.5">
            <div className="flex items-center justify-between">
                <strong>{place.name}</strong>
                <Button type="button" variant="destructive" size="sm" onClick={onRemove}>
                    {t('integration_remove_place_action')}
                </Button>
            </div>

            <FormField htmlFor={`externalId-${place.id}`} label={t('integration_external_id_label')} error={errors.externalId}>
                <Input
                    id={`externalId-${place.id}`}
                    type="url"
                    value={data.externalId}
                    onChange={(e) => setData('externalId', e.target.value)}
                />
            </FormField>

            <Button type="submit" disabled={processing} className="justify-self-start">
                {t('update_integration')}
            </Button>
        </form>
    );
}
