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
                    <div className="relative overflow-hidden rounded-lg border border-neutral-200 bg-white py-3.5 pr-4 pl-[18px]">
                        <span className="absolute inset-y-0 left-0 w-[3px] bg-primary-500" aria-hidden="true" />
                        <p className="m-0 text-[11px] font-bold tracking-wide text-neutral-400 uppercase">{t('place_devices_heading')}</p>
                        <p className="m-0 mt-2 font-mono text-2xl font-bold text-neutral-900 tabular-nums">{devices.length}</p>
                    </div>
                    <div className="relative overflow-hidden rounded-lg border border-neutral-200 bg-white py-3.5 pr-4 pl-[18px]">
                        <span className="absolute inset-y-0 left-0 w-[3px] bg-primary-500" aria-hidden="true" />
                        <p className="m-0 text-[11px] font-bold tracking-wide text-neutral-400 uppercase">{t('place_bookings_recent_heading')}</p>
                        <p className="m-0 mt-2 font-mono text-2xl font-bold text-neutral-900 tabular-nums">{bookings.length}</p>
                    </div>
                    <div className="relative overflow-hidden rounded-lg border border-neutral-200 bg-white py-3.5 pr-4 pl-[18px]">
                        <span className="absolute inset-y-0 left-0 w-[3px] bg-primary-500" aria-hidden="true" />
                        <p className="m-0 text-[11px] font-bold tracking-wide text-neutral-400 uppercase">{t('place_active_codes_heading')}</p>
                        <p className="m-0 mt-2 font-mono text-2xl font-bold text-neutral-900 tabular-nums">{activeAccessCodes}</p>
                    </div>
                </div>

                <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white">
                    <div className="flex items-center justify-between border-b border-neutral-200 px-4.5 py-3">
                        <span className="text-xs font-bold tracking-wide text-neutral-500 uppercase">{t('members')}</span>
                        {abilities.manageMembers ? (
                            <Button asChild size="sm">
                                <Link href={places.members.url({ place: place.id })}>{t('manage_members')}</Link>
                            </Button>
                        ) : null}
                    </div>
                    {placeUsers.length > 0 ? (
                        placeUsers.map((placeUser) => (
                            <div key={placeUser.id} className="flex items-center gap-3 border-b border-neutral-100 px-4.5 py-3 text-[13.5px] last:border-b-0">
                                <span className="flex-1 font-semibold text-neutral-900">
                                    {placeUser.user?.name}
                                    {placeUser.label ? ` (${placeUser.label})` : ''}
                                </span>
                                <span className="rounded-full bg-primary-100 px-2.5 py-0.5 text-[11px] font-bold tracking-wide text-primary-700 uppercase">
                                    {placeUser.role === 'admin' ? t('place_roles.admin') : t('place_roles.host')}
                                </span>
                            </div>
                        ))
                    ) : (
                        <p className="m-0 px-4.5 py-3 text-[13.5px] text-neutral-500">{t('place_no_members_message')}</p>
                    )}
                </div>

                <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white">
                    <div className="flex items-center justify-between border-b border-neutral-200 px-4.5 py-3">
                        <span className="text-xs font-bold tracking-wide text-neutral-500 uppercase">{t('place_devices_heading')}</span>
                        <Button asChild size="sm">
                            <Link href={places.devices.attach.url({ place: place.id })}>{t('place_add_device_action')}</Link>
                        </Button>
                    </div>
                    {devices.length > 0 ? (
                        devices.map((device) => (
                            <div
                                key={device.id}
                                className="flex flex-wrap items-center gap-x-3 gap-y-1.5 border-b border-neutral-100 px-4.5 py-3 last:border-b-0"
                            >
                                <Link
                                    href={devicesRoutes.show.url({ device: device.id })}
                                    className="flex-1 text-[13.5px] font-semibold text-neutral-900 no-underline hover:text-primary-700"
                                >
                                    {device.name}
                                </Link>
                                <span className="text-[12.5px] text-neutral-500">({device.brand})</span>
                                <button
                                    type="button"
                                    onClick={() => setDeviceToRemove(device)}
                                    className="rounded-md border border-error-300 bg-error-100 px-2.5 py-1 text-[12px] font-semibold text-error-700 hover:bg-error-300/40"
                                >
                                    {t('place_remove_device_action')}
                                </button>
                            </div>
                        ))
                    ) : (
                        <p className="m-0 px-4.5 py-3 text-[13.5px] text-neutral-500">{t('place_no_devices_message')}</p>
                    )}
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
