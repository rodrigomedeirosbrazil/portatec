import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import { Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import devicesRoutes from '@/routes/app/devices';
import places from '@/routes/app/places';
import type { Device, Place } from '@/types';

interface PlacesShowProps {
    place: Place;
    activeAccessCodes: number;
    abilities: {
        manageMembers: boolean;
        replicate: boolean;
        update: boolean;
    };
    [key: string]: unknown;
}

export default function PlacesShow({ place, activeAccessCodes, abilities }: PlacesShowProps) {
    const { t } = useTranslations();
    const [deviceToRemove, setDeviceToRemove] = useState<Device | null>(null);

    const devices = place.devices ?? [];
    const bookings = place.bookings ?? [];
    const placeUsers = place.place_users ?? [];

    function confirmRemoveDevice() {
        if (!deviceToRemove) {
            return;
        }

        router.delete(places.devices.destroy.url({ place: place.id, device: deviceToRemove.id }), {
            onFinish: () => setDeviceToRemove(null),
        });
    }

    const actions = (
        <>
            <Button asChild>
                <Link href={places.control.url({ place: place.id })}>{t('place_control_action')}</Link>
            </Button>
            <Button asChild variant="outline">
                <Link href={places.edit.url({ place: place.id })}>{t('place_edit_action')}</Link>
            </Button>
            {abilities.manageMembers ? (
                <Button asChild variant="outline">
                    <Link href={places.members.url({ place: place.id })}>{t('manage_members')}</Link>
                </Button>
            ) : null}
            {abilities.replicate ? (
                <Button asChild variant="outline">
                    <Link href={places.clone.url({ place: place.id })}>{t('clone_place')}</Link>
                </Button>
            ) : null}
        </>
    );

    return (
        <AppLayout>
            <Head title={place.name} />

            <Page>
                <PageHeader title={place.name} backHref={places.index.url()} actions={actions} />

                <div className="grid grid-cols-[repeat(auto-fit,minmax(220px,1fr))] gap-3">
                    <div className="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                        <strong>{t('place_devices_heading')}</strong>
                        <p className="m-0 mt-1.5">{devices.length}</p>
                    </div>
                    <div className="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                        <strong>{t('place_bookings_recent_heading')}</strong>
                        <p className="m-0 mt-1.5">{bookings.length}</p>
                    </div>
                    <div className="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                        <strong>{t('place_active_codes_heading')}</strong>
                        <p className="m-0 mt-1.5">{activeAccessCodes}</p>
                    </div>
                </div>

                <div className="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="mt-0">{t('members')}</h2>
                        {abilities.manageMembers ? (
                            <Button asChild size="sm">
                                <Link href={places.members.url({ place: place.id })}>{t('manage_members')}</Link>
                            </Button>
                        ) : null}
                    </div>
                    <ul className="m-0 pl-5">
                        {placeUsers.length > 0 ? (
                            placeUsers.map((placeUser) => (
                                <li key={placeUser.id}>
                                    {placeUser.user?.name}
                                    {placeUser.label ? ` (${placeUser.label})` : ''} —{' '}
                                    {placeUser.role === 'admin' ? t('place_roles.admin') : t('place_roles.host')}
                                </li>
                            ))
                        ) : (
                            <li className="text-neutral-500">{t('place_no_members_message')}</li>
                        )}
                    </ul>
                </div>

                <div className="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                    <div className="mb-3 flex items-center justify-between">
                        <h2 className="mt-0">{t('place_devices_heading')}</h2>
                        <Button asChild size="sm">
                            <Link href={places.devices.attach.url({ place: place.id })}>{t('place_add_device_action')}</Link>
                        </Button>
                    </div>
                    <ul className="m-0 pl-5">
                        {devices.length > 0 ? (
                            devices.map((device) => (
                                <li key={device.id} className="flex flex-wrap items-center gap-2 py-1">
                                    <Link
                                        href={devicesRoutes.show.url({ device: device.id })}
                                        className="text-primary-700 no-underline hover:text-primary-500"
                                    >
                                        {device.name}
                                    </Link>
                                    <span className="text-neutral-500">({device.brand})</span>
                                    <button
                                        type="button"
                                        onClick={() => setDeviceToRemove(device)}
                                        className="rounded border border-red-200 bg-red-50 px-2 py-1 text-sm text-red-700 hover:bg-red-100"
                                    >
                                        {t('place_remove_device_action')}
                                    </button>
                                </li>
                            ))
                        ) : (
                            <li>{t('place_no_devices_message')}</li>
                        )}
                    </ul>
                </div>
            </Page>

            <ConfirmDialog
                open={deviceToRemove !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeviceToRemove(null);
                    }
                }}
                title={t('place_remove_device_confirm_title', { name: deviceToRemove?.name ?? '' })}
                confirmLabel={t('place_remove_device_action')}
                onConfirm={confirmRemoveDevice}
            />
        </AppLayout>
    );
}
