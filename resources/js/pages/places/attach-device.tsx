import { Head, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';

import { store } from '@/actions/App/Http/Controllers/App/PlaceAttachDeviceController';
import { show } from '@/actions/App/Http/Controllers/App/PlaceController';
import { FormField } from '@/components/form-field';
import { Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import type { Place } from '@/types';

interface AttachableDevice {
    id: number;
    name: string;
    brand: string | null;
    device_functions_count?: number;
    place_names: string[];
    fallback_place_name: string | null;
}

interface AttachDeviceProps {
    place: Place;
    devices: AttachableDevice[];
    [key: string]: unknown;
}

interface AttachDeviceForm {
    deviceId: string;
}

function deviceLocationLabel(device: AttachableDevice, t: (key: string) => string): string {
    if (device.place_names.length > 0) {
        return device.place_names.join(', ');
    }

    return device.fallback_place_name ?? t('attach_device_no_place');
}

export default function AttachDevice({ place, devices }: AttachDeviceProps) {
    const { t } = useTranslations();

    const { data, setData, post, processing, errors } = useForm<AttachDeviceForm>({
        deviceId: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(store.url({ place: place.id }));
    };

    const showUrl = show.url({ place: place.id });

    return (
        <AppLayout>
            <Head title={t('attach_device')} />

            <Page>
                <PageHeader title={t('attach_device')} backHref={showUrl} />

                <p className="text-neutral-600">{t('attach_device_description', { place: place.name })}</p>

                <form onSubmit={submit} className="grid gap-2.5 rounded-[10px] border border-neutral-300 bg-white p-3.5">
                    <FormField htmlFor="deviceId" label={t('attach_device_select')} error={errors.deviceId} required>
                        <Select value={data.deviceId} onValueChange={(value) => setData('deviceId', value)}>
                            <SelectTrigger id="deviceId" className="w-full">
                                <SelectValue placeholder={t('attach_device_select_placeholder')} />
                            </SelectTrigger>
                            <SelectContent>
                                {devices.map((device) => (
                                    <SelectItem key={device.id} value={String(device.id)}>
                                        {device.name} ({device.brand}) — {deviceLocationLabel(device, t)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>

                    <Button type="submit" disabled={processing || devices.length === 0}>
                        {t('attach_device_submit')}
                    </Button>
                </form>

                {devices.length === 0 ? <p className="mt-4 text-neutral-500">{t('attach_device_empty')}</p> : null}
            </Page>
        </AppLayout>
    );
}
