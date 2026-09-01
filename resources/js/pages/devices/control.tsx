import { Head } from '@inertiajs/react';
import axios from 'axios';

import { storeForDevice as sendCommandAction } from '@/actions/App/Http/Controllers/App/DeviceCommandController';
import { show } from '@/actions/App/Http/Controllers/App/DeviceController';
import { FunctionRow } from '@/components/device-control/function-row';
import { Page, PageHeader } from '@/components/page';
import { StatusBadge } from '@/components/status-badge';
import type { DeviceCommandKind, DeviceCommandResult } from '@/hooks/use-device-commands';
import { useDeviceCommands } from '@/hooks/use-device-commands';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';

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

interface ControlPageProps {
    device: ControlDevice;
    placeId: number;
    initialFunctionStatus: Record<string, unknown>;
    [key: string]: unknown;
}

/**
 * Tela de controle de UM dispositivo, em tempo real. Porta `Devices\Control`
 * (Livewire) + `control.blade.php` (a máquina de estados Alpine) 1:1 — a
 * máquina de estados em si já vem pronta e testada de `useDeviceCommands`;
 * esta tela só cuida do layout e do envio do comando.
 *
 * Os canais de realtime são por local (`Place.Device.*.{placeId}`), não por
 * dispositivo — `placeId` aqui é o mesmo resolvido no controller, espelhando
 * o `$placeId` computado hoje em `control.blade.php`.
 */
export default function DeviceControl({ device, placeId, initialFunctionStatus }: ControlPageProps) {
    const { t } = useTranslations();

    const commands = useDeviceCommands({
        placeId: placeId || null,
        initialFunctionStatus,
        initialDeviceAvailability: { [String(device.id)]: device.is_available },
        sendCommand: async ({ action, pin }): Promise<DeviceCommandResult> => {
            const response = await axios.post<{ commandId: string | null }>(sendCommandAction.url({ device: device.id }), {
                action,
                pin,
            });

            return { commandId: response.data.commandId ?? null };
        },
    });

    const triggerAction = (functionType: ControllableFunction['type']): DeviceCommandKind =>
        functionType === 'button' ? 'push_button' : 'toggle';

    return (
        <AppLayout>
            <Head title={t('device_control_title', { device: device.name })} />

            <Page>
                <PageHeader
                    title={t('device_control_title', { device: device.name })}
                    backHref={show.url({ device: device.id })}
                />

                <div className="rounded-lg border border-neutral-200 bg-white p-3.5">
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="m-0">{t('device_control_actions')}</h2>
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
                                    device.status_function ? commands.getFunctionStatus(device.id, device.status_function.pin) : null
                                }
                                onTrigger={() => commands.trigger(device.id, controlFunction.pin, triggerAction(controlFunction.type))}
                            />
                        ))
                    ) : device.is_tuya_lock ? (
                        <p className="m-0 text-neutral-500">{t('device_control_tuya_lock_message')}</p>
                    ) : (
                        <p className="m-0 text-neutral-500">{t('device_control_no_functions')}</p>
                    )}
                </div>
            </Page>
        </AppLayout>
    );
}
