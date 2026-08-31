import { Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState, type ReactNode } from 'react';

import app from '@/routes/app';
import { logout } from '@/routes';
import { NavLink } from '@/components/nav-link';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';

interface AppLayoutPageProps {
    auth: {
        user: { is_super_admin: boolean } | null;
    };
    impersonation: {
        active: boolean;
    };
    flash: {
        status: string | null;
    };
    [key: string]: unknown;
}

export interface AppLayoutProps {
    children: ReactNode;
}

export function AppLayout({ children }: AppLayoutProps) {
    const { props } = usePage<AppLayoutPageProps>();
    const { auth, impersonation, flash } = props;

    // O painel /admin so aceita super admin (User::canAccessPanel). Sem esta
    // checagem o item aparecia para todo mundo e levava a um 403 - inclusive em
    // sessao assumida, quando o usuario efetivo e o cliente.
    const canAccessAdminPanel = auth.user?.is_super_admin === true;
    const { t } = useTranslations();

    const [open, setOpen] = useState(false);
    const navRef = useRef<HTMLElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        function handleClickAway(event: MouseEvent) {
            if (navRef.current && !navRef.current.contains(event.target as Node)) {
                setOpen(false);
            }
        }

        document.addEventListener('mousedown', handleClickAway);

        return () => document.removeEventListener('mousedown', handleClickAway);
    }, [open]);

    const dashboardUrl = app.dashboard.url();
    const placesUrl = app.places.index.url();
    const devicesUrl = app.devices.index.url();
    const bookingsUrl = app.bookings.index.url();
    const accessCodesUrl = app.accessCodes.index.url();
    const bookingsIntegrationsUrl = app.bookings.integrations.index.url();
    const adminUrl = '/admin';

    return (
        <>
            <nav ref={navRef} className="relative border-b border-neutral-200 bg-white shadow-sm">
                <div className="flex items-center justify-between px-5 py-3">
                    {/* Mobile: hamburger + logo */}
                    <div className="flex items-center gap-3 md:hidden">
                        <button
                            type="button"
                            onClick={() => setOpen((value) => !value)}
                            aria-expanded={open}
                            aria-label={open ? t('nav_close_menu') : t('nav_open_menu')}
                            className="rounded-md p-2 text-neutral-700 hover:bg-neutral-100 focus:outline-none focus:ring-2 focus:ring-primary-500"
                        >
                            <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <Link href={dashboardUrl} className="no-underline">
                            <img
                                src="/images/logo/portatec-logo-branco-horizontal.png"
                                alt="Portatec"
                                className="h-8 w-auto"
                            />
                        </Link>
                    </div>

                    {/* Desktop: logo + links + Sair */}
                    <div className="hidden md:flex md:items-center md:gap-6">
                        <Link href={dashboardUrl} className="no-underline">
                            <img
                                src="/images/logo/portatec-logo-branco-horizontal.png"
                                alt="Portatec"
                                className="h-8 w-auto"
                            />
                        </Link>
                        <NavLink href={placesUrl} pattern="/app/places*">
                            {t('nav_places')}
                        </NavLink>
                        <NavLink href={devicesUrl} pattern="/app/devices*">
                            {t('nav_devices')}
                        </NavLink>
                        <NavLink href={bookingsUrl} pattern="/app/bookings*" exclude="/app/bookings/integrations*">
                            {t('nav_bookings')}
                        </NavLink>
                        <NavLink href={accessCodesUrl} pattern="/app/access-codes*">
                            {t('nav_access_codes')}
                        </NavLink>
                        <NavLink href={bookingsIntegrationsUrl} pattern="/app/bookings/integrations*">
                            {t('nav_bookings_integrations')}
                        </NavLink>
                        {canAccessAdminPanel && (
                            <NavLink href={adminUrl} pattern="/admin*" external>
                                {t('nav_admin')}
                            </NavLink>
                        )}
                        <Link
                            href={logout.url()}
                            method="post"
                            as="button"
                            className="ml-auto cursor-pointer rounded-md border-0 bg-primary-500 px-3 py-2 text-white hover:bg-primary-700"
                        >
                            {t('nav_logout')}
                        </Link>
                    </div>
                </div>

                {/* Mobile dropdown menu */}
                <div
                    aria-hidden={!open}
                    className={cn(
                        'absolute left-0 right-0 top-full z-50 border-b border-neutral-200 bg-white shadow-lg transition duration-200 ease-out md:hidden',
                        open ? 'pointer-events-auto translate-y-0 opacity-100' : 'pointer-events-none -translate-y-2 opacity-0',
                    )}
                >
                    <div className="flex flex-col gap-1 px-5 py-3">
                        <NavLink href={dashboardUrl} pattern="/app/dashboard" mobile>
                            {t('nav_dashboard')}
                        </NavLink>
                        <NavLink href={placesUrl} pattern="/app/places*" mobile>
                            {t('nav_places')}
                        </NavLink>
                        <NavLink href={devicesUrl} pattern="/app/devices*" mobile>
                            {t('nav_devices')}
                        </NavLink>
                        <NavLink href={bookingsUrl} pattern="/app/bookings*" exclude="/app/bookings/integrations*" mobile>
                            {t('nav_bookings')}
                        </NavLink>
                        <NavLink href={accessCodesUrl} pattern="/app/access-codes*" mobile>
                            {t('nav_access_codes')}
                        </NavLink>
                        <NavLink href={bookingsIntegrationsUrl} pattern="/app/bookings/integrations*" mobile>
                            {t('nav_bookings_integrations')}
                        </NavLink>
                        {canAccessAdminPanel && (
                            <NavLink href={adminUrl} pattern="/admin*" mobile external>
                                {t('nav_admin')}
                            </NavLink>
                        )}
                        <Link
                            href={logout.url()}
                            method="post"
                            as="button"
                            className="w-full cursor-pointer rounded-md border-0 bg-primary-500 px-3 py-2 text-left text-white hover:bg-primary-700"
                        >
                            {t('nav_logout')}
                        </Link>
                    </div>
                </div>
            </nav>

            <main className="mx-auto max-w-[980px] px-4 py-6">
                {impersonation.active && (
                    <div className="mb-4 flex items-center justify-between gap-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2.5 text-amber-800">
                        <span>{t('impersonation_active_message')}</span>
                        <Link
                            href={app.impersonations.stop.url()}
                            method="post"
                            as="button"
                            className="cursor-pointer rounded-md border-0 bg-amber-800 px-3 py-2 text-white"
                        >
                            {t('impersonation_stop')}
                        </Link>
                    </div>
                )}

                {flash.status && (
                    <div className="mb-4 rounded-lg border border-success-300 bg-success-100 px-3 py-2.5 text-success-700">
                        {flash.status}
                    </div>
                )}

                {children}
            </main>
        </>
    );
}

export default AppLayout;
