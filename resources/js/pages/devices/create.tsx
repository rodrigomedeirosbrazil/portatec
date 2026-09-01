import { Head, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';

import { FormField } from '@/components/form-field';
import { Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import devices from '@/routes/app/devices';
import type { DeviceBrand, Place } from '@/types';

interface DeviceCreateProps {
    places: Place[];
    placeIds: number[];
    brands: DeviceBrand[];
    [key: string]: unknown;
}

interface DeviceCreateForm {
    placeIds: number[];
    name: string;
    brand: DeviceBrand;
    external_device_id: string;
    default_pin: string;
}

export default function DeviceCreate({ places, placeIds, brands }: DeviceCreateProps) {
    const { t } = useTranslations();
    const { data, setData, post, processing, errors } = useForm<DeviceCreateForm>({
        placeIds,
        name: '',
        brand: brands[0] ?? 'portatec',
        external_device_id: '',
        default_pin: '',
    });

    const togglePlace = (placeId: number, checked: boolean) => {
        setData('placeIds', checked ? [...data.placeIds, placeId] : data.placeIds.filter((id) => id !== placeId));
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(devices.store.url());
    };

    return (
        <AppLayout>
            <Head title={t('new_device')} />

            <Page>
                <PageHeader title={t('new_device')} backHref={devices.index.url()} />

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

                    <Button type="submit" disabled={processing} className="mt-1 justify-self-start">
                        {t('save_device')}
                    </Button>
                </form>
            </Page>
        </AppLayout>
    );
}
