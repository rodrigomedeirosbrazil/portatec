import { Head, Link } from '@inertiajs/react';

import { Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import devices from '@/routes/app/devices';
import type { AccessCodeDeviceSync, CommandLog, Device } from '@/types';

interface DevicesShowProps {
    device: Device;
    recentCommands: CommandLog[];
    recentTuyaSyncs: AccessCodeDeviceSync[];
    [key: string]: unknown;
}

export default function DevicesShow({ device, recentCommands, recentTuyaSyncs }: DevicesShowProps) {
    const { t } = useTranslations();

    const locationsLabel = (device.places ?? []).map((place) => place.name).join(', ') || device.place?.name || t('unassigned_place');

    const headerActions = (
        <>
            <Button variant="outline" asChild>
                <Link href={devices.edit.url({ device: device.id })}>{t('edit')}</Link>
            </Button>
            <Button asChild>
                <Link href={devices.control.url({ device: device.id })}>{t('control')}</Link>
            </Button>
        </>
    );

    return (
        <AppLayout>
            <Head title={device.name} />

            <Page>
                <PageHeader title={device.name} backHref={devices.index.url()} actions={headerActions} />

                <div className="grid grid-cols-[repeat(auto-fit,minmax(220px,1fr))] gap-3">
                    <div className="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                        <strong>{t('places')}</strong>
                        <p className="mt-1.5 m-0">{locationsLabel}</p>
                    </div>
                    <div className="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                        <strong>{t('brand')}</strong>
                        <p className="mt-1.5 m-0">{device.brand}</p>
                    </div>
                    <div className="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                        <strong>{t('status')}</strong>
                        <p className="mt-1.5 m-0">{device.is_available ? t('online') : t('offline')}</p>
                    </div>
                    <div className="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                        <strong>{t('last_sync')}</strong>
                        <p className="mt-1.5 m-0">{device.last_sync ? formatDateTime(device.last_sync) : t('never_synced')}</p>
                    </div>
                </div>

                <div className="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                    <h2 className="mt-0">{t('device_functions')}</h2>
                    <ul className="m-0 pl-5">
                        {device.device_functions && device.device_functions.length > 0 ? (
                            device.device_functions.map((deviceFunction) => (
                                <li key={deviceFunction.id}>
                                    {t('device_function_line', { type: deviceFunction.type_label ?? '', pin: deviceFunction.pin })}
                                    {deviceFunction.status !== null ? (
                                        <>
                                            {' | '}
                                            {t('status')}: {deviceFunction.status ? t('device_on') : t('device_off')}
                                        </>
                                    ) : null}
                                </li>
                            ))
                        ) : device.supports_tuya_temporary_password ? (
                            <li>{t('tuya_lock_pins_from_access_codes')}</li>
                        ) : device.is_tuya_lock ? (
                            <li className="text-error-500">{t('tuya_lock_no_temp_password_dp')}</li>
                        ) : (
                            <li>{t('device_no_functions')}</li>
                        )}
                    </ul>
                </div>

                <div className="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                    <h2 className="mt-0">{device.is_tuya_lock ? t('device_recent_syncs_title') : t('device_recent_commands_title')}</h2>
                    <ul className="m-0 pl-5">
                        {device.is_tuya_lock ? (
                            <>
                                {!device.supports_tuya_temporary_password ? (
                                    <li className="text-error-500">{t('tuya_lock_no_temp_password_dp')}</li>
                                ) : null}
                                {recentTuyaSyncs.length > 0 ? (
                                    recentTuyaSyncs.map((sync) => (
                                        <li key={sync.id}>
                                            {sync.updated_at ? formatDateTime(sync.updated_at) : ''} - {sync.status.toUpperCase()}
                                            {sync.synced_pin ? <> - {t('pin')} {sync.synced_pin}</> : null}
                                            {sync.error_message ? <> - {sync.error_message}</> : null}
                                        </li>
                                    ))
                                ) : (
                                    <li>{t('device_no_syncs')}</li>
                                )}
                            </>
                        ) : recentCommands.length > 0 ? (
                            recentCommands.map((command) => (
                                <li key={command.id}>
                                    {command.created_at ? formatDateTime(command.created_at) : ''} - {command.command_type}
                                </li>
                            ))
                        ) : (
                            <li>{t('device_no_commands')}</li>
                        )}
                    </ul>
                </div>
            </Page>
        </AppLayout>
    );
}

function formatDateTime(isoString: string): string {
    const date = new Date(isoString);

    return date.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}
