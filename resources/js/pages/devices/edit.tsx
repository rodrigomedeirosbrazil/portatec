import { Head, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';

import { FormField } from '@/components/form-field';
import { Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import devices from '@/routes/app/devices';
import type { Device, DeviceBrand, DeviceType, Place } from '@/types';

interface DeviceFunctionRow {
    id: number | null;
    type: DeviceType;
    pin: string;
}

interface DeviceEditProps {
    device: Device;
    places: Place[];
    placeIds: number[];
    deviceFunctions: DeviceFunctionRow[];
    brands: DeviceBrand[];
    deviceTypes: DeviceType[];
    [key: string]: unknown;
}

interface DeviceEditForm {
    placeIds: number[];
    name: string;
    brand: DeviceBrand;
    external_device_id: string;
    default_pin: string;
    deviceFunctions: DeviceFunctionRow[];
}

export default function DeviceEdit({ device, places, placeIds, deviceFunctions, brands, deviceTypes }: DeviceEditProps) {
    const { t } = useTranslations();
    const { data, setData, put, processing, errors } = useForm<DeviceEditForm>({
        placeIds,
        name: device.name,
        brand: (device.brand ?? brands[0] ?? 'portatec') as DeviceBrand,
        external_device_id: device.external_device_id ?? '',
        default_pin: device.default_pin ?? '',
        deviceFunctions,
    });

    const togglePlace = (placeId: number, checked: boolean) => {
        setData('placeIds', checked ? [...data.placeIds, placeId] : data.placeIds.filter((id) => id !== placeId));
    };

    const addFunction = () => {
        setData('deviceFunctions', [...data.deviceFunctions, { id: null, type: deviceTypes[0] ?? 'switch', pin: '' }]);
    };

    const removeFunction = (index: number) => {
        setData(
            'deviceFunctions',
            data.deviceFunctions.filter((_, i) => i !== index),
        );
    };

    const updateFunction = (index: number, changes: Partial<DeviceFunctionRow>) => {
        setData(
            'deviceFunctions',
            data.deviceFunctions.map((fn, i) => (i === index ? { ...fn, ...changes } : fn)),
        );
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(devices.update.url({ device: device.id }));
    };

    // `errors` is keyed by `keyof DeviceEditForm`, but Laravel returns
    // dotted keys for array fields (e.g. `deviceFunctions.0.type`) that
    // aren't part of that literal key set.
    const fieldErrors = errors as Record<string, string>;

    return (
        <AppLayout>
            <Head title={t('edit_device')} />

            <Page>
                <PageHeader title={t('edit_device')} backHref={devices.show.url({ device: device.id })} />

                <form onSubmit={submit} className="grid gap-4 rounded-lg border border-neutral-200 bg-white p-3.5">
                    <FormField
                        htmlFor="placeIds"
                        label={t('places')}
                        error={errors.placeIds}
                        description={t('device_places_description')}
                        required
                    >
                        <div id="placeIds" className="grid gap-2 rounded-lg border border-input p-2">
                            {places.map((place) => (
                                <label key={place.id} className="flex items-center gap-2 text-sm font-normal">
                                    <Checkbox
                                        checked={data.placeIds.includes(place.id)}
                                        onCheckedChange={(checked) => togglePlace(place.id, checked === true)}
                                    />
                                    {place.name}
                                </label>
                            ))}
                        </div>
                    </FormField>

                    <FormField htmlFor="name" label={t('name')} error={errors.name} required>
                        <Input
                            id="name"
                            type="text"
                            required
                            autoFocus
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                        />
                    </FormField>

                    <FormField htmlFor="brand" label={t('brand')} error={errors.brand} required>
                        <Select value={data.brand} onValueChange={(value) => setData('brand', value as DeviceBrand)}>
                            <SelectTrigger id="brand" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {brands.map((brand) => (
                                    <SelectItem key={brand} value={brand}>
                                        {brand}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>

                    <FormField htmlFor="external_device_id" label={`${t('external_device_id')} (${t('optional')})`} error={errors.external_device_id}>
                        <Input
                            id="external_device_id"
                            type="text"
                            value={data.external_device_id}
                            onChange={(e) => setData('external_device_id', e.target.value)}
                        />
                    </FormField>

                    <FormField
                        htmlFor="default_pin"
                        label={`${t('default_pin')} (${t('optional')})`}
                        error={errors.default_pin}
                        description={t('default_pin_helper')}
                    >
                        <Input
                            id="default_pin"
                            type="text"
                            maxLength={6}
                            inputMode="numeric"
                            pattern="[0-9]*"
                            value={data.default_pin}
                            onChange={(e) => setData('default_pin', e.target.value)}
                        />
                    </FormField>

                    <div className="mt-2 border-t border-neutral-200 pt-4">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="mt-0 text-lg">{t('device_functions')}</h2>
                            <Button type="button" variant="outline" size="sm" onClick={addFunction}>
                                {t('add_device_function')}
                            </Button>
                        </div>

                        {data.deviceFunctions.map((fn, index) => (
                            <div key={index} className="mb-3 flex flex-wrap items-end gap-2 rounded-lg border border-neutral-200 bg-neutral-50 p-3">
                                <div className="min-w-[160px] flex-1 space-y-1.5">
                                    <Label htmlFor={`fn-type-${index}`}>{t('type')}</Label>
                                    <Select
                                        value={fn.type}
                                        onValueChange={(value) => updateFunction(index, { type: value as DeviceType })}
                                    >
                                        <SelectTrigger id={`fn-type-${index}`} className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {deviceTypes.map((type) => (
                                                <SelectItem key={type} value={type}>
                                                    {t(`device_types.${type}`)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {fieldErrors[`deviceFunctions.${index}.type`] ? (
                                        <p className="text-sm text-destructive">{fieldErrors[`deviceFunctions.${index}.type`]}</p>
                                    ) : null}
                                </div>

                                <div className="min-w-[140px] flex-1 space-y-1.5">
                                    <Label htmlFor={`fn-pin-${index}`}>{t('pin')}</Label>
                                    <Input
                                        id={`fn-pin-${index}`}
                                        type="text"
                                        value={fn.pin}
                                        onChange={(e) => updateFunction(index, { pin: e.target.value })}
                                    />
                                    {fieldErrors[`deviceFunctions.${index}.pin`] ? (
                                        <p className="text-sm text-destructive">{fieldErrors[`deviceFunctions.${index}.pin`]}</p>
                                    ) : null}
                                </div>

                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    disabled={data.deviceFunctions.length <= 1}
                                    onClick={() => removeFunction(index)}
                                >
                                    {t('device_function_remove')}
                                </Button>
                            </div>
                        ))}

                        {errors.deviceFunctions ? <p className="text-sm text-destructive">{errors.deviceFunctions}</p> : null}
                    </div>

                    <Button type="submit" disabled={processing} className="mt-1 justify-self-start">
                        {t('update_device')}
                    </Button>
                </form>
            </Page>
        </AppLayout>
    );
}
