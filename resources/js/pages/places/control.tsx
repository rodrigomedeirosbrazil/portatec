import { Head, Link } from '@inertiajs/react';
import axios from 'axios';
import { useMemo } from 'react';

import { store as sendCommandAction } from '@/actions/App/Http/Controllers/App/DeviceCommandController';
import { show } from '@/actions/App/Http/Controllers/App/PlaceController';
import { FunctionRow } from '@/components/device-control/function-row';
import { Page, PageHeader } from '@/components/page';
import { StatusBadge } from '@/components/status-badge';
import type { DeviceCommandKind, DeviceCommandResult } from '@/hooks/use-device-commands';
import { useDeviceCommands } from '@/hooks/use-device-commands';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import app from '@/routes/app';

interface ControllableFunction {
    pin: string;
    type: 'button' | 'switch';
    type_label: string;
}

interface StatusFunction {
    pin: string;
    status: unknown;
}

interface ControlDevice {
    id: number;
    name: string;
    is_available: boolean;
    is_tuya_lock: boolean;
    controllable_functions: ControllableFunction[];
    status_function: StatusFunction | null;
}

interface ControlPlace {
    id: number;
    name: string;
}

interface ControlPageProps {
    place: ControlPlace;
    devices: ControlDevice[];
    initialFunctionStatus: Record<string, unknown>;
    [key: string]: unknown;
}

/**
 * Tela de controle de dispositivos de um local, em tempo real. Porta
 * `Places\Control` (Livewire) + `control.blade.php` (a máquina de estados
 * Alpine) 1:1 — a máquina de estados em si já vem pronta e testada de
 * `useDeviceCommands`; esta tela só cuida do layout e do envio do comando.
 */
export default function PlaceControl({ place, devices, initialFunctionStatus }: ControlPageProps) {
    const { t } = useTranslations();

    const initialDeviceAvailability = useMemo(
        () => Object.fromEntries(devices.map((device) => [String(device.id), device.is_available])),
        [devices],
    );

    const commands = useDeviceCommands({
        placeId: place.id,
        initialFunctionStatus,
        initialDeviceAvailability,
        sendCommand: async ({ deviceId, action, pin }): Promise<DeviceCommandResult> => {
            const response = await axios.post<{ commandId: string | null }>(sendCommandAction.url({ place: place.id }), {
                device_id: deviceId,
                action,
                pin,
            });

            return { commandId: response.data.commandId ?? null };
        },
    });

    const triggerAction = (functionType: ControllableFunction['type']): DeviceCommandKind =>
        functionType === 'button' ? 'push_button' : 'toggle';

    return (
        <AppLayout
            breadcrumbs={[
                { label: t('nav_control'), href: app.control.index.url() },
                { label: place.name },
            ]}
        >
            <Head title={t('place_control_title', { place: place.name })} />

            <Page>
                <PageHeader
                    title={t('place_control_title', { place: place.name })}
                    backHref={show.url({ place: place.id })}
                    actions={
                        <Link
                            href={app.currentPlace.update.url()}
                            method="post"
                            data={{ place_id: '' }}
                            as="button"
                            className="cursor-pointer rounded-md border border-neutral-200 bg-white px-3 py-1.5 text-[12.5px] font-semibold text-neutral-700"
                        >
                            {t('control_all_places')}
                        </Link>
                    }
                />

                <div className="space-y-4">
                    {devices.length === 0 ? (
                        <p className="m-0 rounded-lg border border-neutral-200 bg-white p-3.5 text-neutral-500">
                            {t('device_control_no_devices')}
                        </p>
                    ) : (
                        devices.map((device) => (
                            <div key={device.id} className="rounded-lg border border-neutral-200 bg-white p-3.5">
                                <div className="mb-3 flex items-center justify-between">
                                    <h2 className="m-0">{device.name}</h2>
                                    <StatusBadge variant={commands.isAvailable(device.id) ? 'success' : 'neutral'}>
                                        {commands.isAvailable(device.id) ? t('online') : t('offline')}
                                    </StatusBadge>
                                </div>

                                {device.controllable_functions.length > 0 ? (
                                    device.controllable_functions.map((controlFunction) => (
                                        <FunctionRow
                                            key={controlFunction.pin}
                                            type={controlFunction.type}
                                            typeLabel={controlFunction.type_label}
                                            pin={controlFunction.pin}
                                            status={commands.getStatus(device.id, controlFunction.pin)}
                                            disabled={commands.isBusy(device.id, controlFunction.pin)}
                                            functionStatus={
                                                device.status_function
                                                    ? commands.getFunctionStatus(device.id, device.status_function.pin)
                                                    : null
                                            }
                                            onTrigger={() =>
                                                commands.trigger(device.id, controlFunction.pin, triggerAction(controlFunction.type))
                                            }
                                        />
                                    ))
                                ) : device.is_tuya_lock ? (
                                    <p className="m-0 text-neutral-500">{t('device_control_tuya_lock_message')}</p>
                                ) : (
                                    <p className="m-0 text-neutral-500">{t('device_control_no_functions')}</p>
                                )}
                            </div>
                        ))
                    )}
                </div>
            </Page>
        </AppLayout>
    );
}
