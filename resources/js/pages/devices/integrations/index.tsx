import { Head, Link } from '@inertiajs/react';

import { EmptyState } from '@/components/empty-state';
import { Page, PageHeader } from '@/components/page';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import devices from '@/routes/app/devices';
import type { Integration } from '@/types';

interface DeviceIntegrationsIndexProps {
    integrations: Integration[];
    [key: string]: unknown;
}

function formatDateTime(iso: string): string {
    return new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));
}

/**
 * Porta `Devices\Integrations\Index` 1:1 — lista as integrações Tuya do
 * usuário. Ver `App\Http\Resources\IntegrationResource` para o motivo de
 * `tuya_user_code` não aparecer mais no cartão: é segredo e não deve
 * trafegar como prop Inertia.
 */
export default function DeviceIntegrationsIndex({ integrations }: DeviceIntegrationsIndexProps) {
    const { t } = useTranslations();

    return (
        <AppLayout
            breadcrumbs={[
                { label: t('nav_devices'), href: devices.index.url() },
                { label: t('integrations') },
            ]}
        >
            <Head title={t('tuya_integrations_title')} />

            <Page>
                <PageHeader
                    title={t('tuya_integrations_title')}
                    subtitle={t('tuya_integrations_subtitle')}
                    backHref={devices.index.url()}
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={devices.integrations.tuyaConnect.url()}>{t('tuya_connect_action')}</Link>
                        </Button>
                    }
                />

                <div className="grid gap-3">
                    {integrations.length === 0 ? (
                        <EmptyState message={t('tuya_no_integrations')} />
                    ) : (
                        integrations.map((integration) => (
                            <article key={integration.id} className="rounded-lg border border-neutral-200 bg-white p-3.5">
                                <div className="mb-2 flex items-center justify-between">
                                    <h2 className="text-lg">{integration.platform?.name ?? t('tuya_fallback_name')}</h2>
                                    <StatusBadge variant="success">{t('tuya_connected_badge')}</StatusBadge>
                                </div>
                                <p className="m-0 text-neutral-500">
                                    {t('tuya_account_label', { uid: integration.tuya_uid ?? t('tuya_not_informed') })}
                                </p>
                                <p className="mt-1 m-0 text-neutral-500">
                                    {t('updated_at')} {integration.updated_at ? formatDateTime(integration.updated_at) : ''}
                                </p>
                            </article>
                        ))
                    )}
                </div>
            </Page>
        </AppLayout>
    );
}
