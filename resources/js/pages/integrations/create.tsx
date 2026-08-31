import { Head, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';

import { FormField } from '@/components/form-field';
import { Page, PageHeader } from '@/components/page';
import { PlaceSelect } from '@/components/place-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import integrationsRoutes from '@/routes/app/bookings/integrations';
import type { Platform, PlaceOption } from '@/types';

interface IntegrationsCreateProps {
    platforms: Platform[];
    places: PlaceOption[];
    platformId: number | null;
    placeId: number | null;
    [key: string]: unknown;
}

interface IntegrationCreateForm {
    platformId: string;
    placeId: string;
    externalId: string;
}

export default function IntegrationsCreate({ platforms, places, platformId, placeId }: IntegrationsCreateProps) {
    const { t } = useTranslations();
    const { data, setData, post, processing, errors } = useForm<IntegrationCreateForm>({
        platformId: platformId !== null ? String(platformId) : '',
        placeId: placeId !== null ? String(placeId) : '',
        externalId: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(integrationsRoutes.store.url());
    };

    return (
        <AppLayout>
            <Head title={t('integration_new_action')} />

            <Page>
                <PageHeader title={t('integration_new_action')} backHref={integrationsRoutes.index.url()} />

                <form onSubmit={submit} className="grid gap-3 rounded-[10px] border border-neutral-300 bg-white p-3.5">
                    <FormField htmlFor="platformId" label={t('platform')} error={errors.platformId} required>
                        <Select value={data.platformId} onValueChange={(value) => setData('platformId', value)} required>
                            <SelectTrigger id="platformId" className="h-auto w-full min-w-0 p-2">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {platforms.map((platform) => (
                                    <SelectItem key={platform.id} value={String(platform.id)}>
                                        {platform.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField htmlFor="placeId" label={t('place')} error={errors.placeId} required>
                        <PlaceSelect id="placeId" value={data.placeId} onChange={(value) => setData('placeId', value)} places={places} required />
                    </FormField>

                    <FormField
                        htmlFor="externalId"
                        label={t('integration_external_id_label')}
                        error={errors.externalId}
                        description={t('integration_ical_helper_text')}
                        required
                    >
                        <Input
                            id="externalId"
                            type="url"
                            required
                            value={data.externalId}
                            onChange={(e) => setData('externalId', e.target.value)}
                        />
                    </FormField>

                    <Button type="submit" disabled={processing} className="mt-1 justify-self-start">
                        {t('save_integration')}
                    </Button>
                </form>
            </Page>
        </AppLayout>
    );
}
