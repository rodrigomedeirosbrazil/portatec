import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

import { ConfirmDialog } from '@/components/confirm-dialog';
import { Page, PageHeader } from '@/components/page';
import { StatTile } from '@/components/stat-tile';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import accessCodesRoutes from '@/routes/app/access-codes';
import bookingsRoutes from '@/routes/app/bookings';
import integrationsRoutes from '@/routes/app/bookings/integrations';
import devicesRoutes from '@/routes/app/devices';
import places from '@/routes/app/places';
import type { Device, Integration, Place } from '@/types';

interface PlacesShowProps {
    place: Place;
    activeAccessCodes: number;
    bookingsCount: number;
    bookingSources: Integration[];
    abilities: {
        manageMembers: boolean;
        replicate: boolean;
        update: boolean;
    };
    [key: string]: unknown;
}

export default function PlacesShow({ place, activeAccessCodes, bookingsCount, bookingSources, abilities }: PlacesShowProps) {
    const { t } = useTranslations();
    const [deviceToRemove, setDeviceToRemove] = useState<Device | null>(null);

    const devices = place.devices ?? [];
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
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="outline" aria-label={t('details')}>
                        …
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem asChild>
                        <Link href={places.edit.url({ place: place.id })}>{t('place_edit_action')}</Link>
                    </DropdownMenuItem>
                    {abilities.manageMembers ? (
                        <DropdownMenuItem asChild>
                            <Link href={places.members.url({ place: place.id })}>{t('manage_members')}</Link>
                        </DropdownMenuItem>
                    ) : null}
                    {abilities.replicate ? (
                        <DropdownMenuItem asChild>
                            <Link href={places.clone.url({ place: place.id })}>{t('clone_place')}</Link>
                        </DropdownMenuItem>
                    ) : null}
                </DropdownMenuContent>
            </DropdownMenu>
        </>
    );

    return (
        <AppLayout
            breadcrumbs={[
                { label: t('nav_places'), href: places.index.url() },
                { label: place.name },
            ]}
        >
            <Head title={place.name} />

            <Page>
                <PageHeader title={place.name} backHref={places.index.url()} actions={actions} />

                <div className="grid grid-cols-[repeat(auto-fit,minmax(220px,1fr))] gap-3">
                    <StatTile
                        label={t('place_devices_heading')}
                        value={devices.length}
                        href={devicesRoutes.index.url({ query: { place_id: place.id } })}
                    />
                    <StatTile
                        label={t('bookings')}
                        value={bookingsCount}
                        href={bookingsRoutes.index.url({ query: { place_id: place.id } })}
                    />
                    <StatTile
                        label={t('place_active_codes_heading')}
                        value={activeAccessCodes}
                        href={accessCodesRoutes.index.url({ query: { place_id: place.id } })}
                    />
                </div>

                <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white">
                    <div className="flex items-center justify-between border-b border-neutral-200 px-4.5 py-3">
                        <span className="text-xs font-bold tracking-wide text-neutral-500 uppercase">
                            {t('place_booking_sources_heading')}
                        </span>
                        <Button asChild size="sm">
                            <Link href={integrationsRoutes.create.url({ query: { place_id: place.id } })}>
                                {t('place_add_booking_source')}
                            </Link>
                        </Button>
                    </div>
                    {bookingSources.length > 0 ? (
                        bookingSources.map((source) => (
                            <div key={source.id} className="flex items-center gap-3 border-b border-neutral-100 px-4.5 py-3 text-[13.5px] last:border-b-0">
                                <Link
                                    href={integrationsRoutes.edit.url({ integration: source.id })}
                                    className="flex-1 font-semibold text-neutral-900 no-underline hover:text-primary-700"
                                >
                                    {source.platform?.name ?? t('platform')}
                                </Link>
                                <span className="text-[12.5px] text-neutral-500">
                                    {source.updated_at ? new Date(source.updated_at).toLocaleString('pt-BR') : ''}
                                </span>
                            </div>
                        ))
                    ) : (
                        <p className="m-0 px-4.5 py-3 text-[13.5px] text-neutral-500">{t('place_no_booking_sources')}</p>
                    )}
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
