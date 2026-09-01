import { Link, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';

import app from '@/routes/app';
import { logout } from '@/routes';
import { NavLink, isNavLinkActive } from '@/components/nav-link';
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

const ITEM_ICON_CLASS = 'h-4 w-4 flex-shrink-0';

function DashboardIcon() {
    return (
        <svg className={ITEM_ICON_CLASS} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <rect x="3" y="3" width="8" height="8" rx="1.5" />
            <rect x="13" y="3" width="8" height="8" rx="1.5" />
            <rect x="3" y="13" width="8" height="8" rx="1.5" />
            <rect x="13" y="13" width="8" height="8" rx="1.5" />
        </svg>
    );
}

function PlacesIcon() {
    return (
        <svg className={ITEM_ICON_CLASS} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="M12 3 L20 8 L20 20 L4 20 L4 8 Z" />
        </svg>
    );
}

function DevicesIcon() {
    return (
        <svg className={ITEM_ICON_CLASS} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="8" />
            <circle cx="12" cy="12" r="2.5" />
        </svg>
    );
}

function BookingsIcon() {
    return (
        <svg className={ITEM_ICON_CLASS} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <rect x="4" y="5" width="16" height="15" rx="2" />
            <line x1="4" y1="10" x2="20" y2="10" />
        </svg>
    );
}

function AccessCodesIcon() {
    return (
        <svg className={ITEM_ICON_CLASS} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <rect x="5" y="4" width="12" height="16" rx="2" />
            <circle cx="13.5" cy="12" r="0.8" fill="currentColor" stroke="none" />
        </svg>
    );
}

function IntegrationsIcon() {
    return (
        <svg className={ITEM_ICON_CLASS} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="M4 12 A8 8 0 1 1 8 18.9" />
            <path d="M4 12 L4 7 M4 12 L8.5 12" />
        </svg>
    );
}

function AdminIcon() {
    return (
        <svg className={ITEM_ICON_CLASS} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="M12 3 L20 6 L20 12 C20 17 16.5 20.5 12 22 C7.5 20.5 4 17 4 12 L4 6 Z" />
        </svg>
    );
}

function LogoutIcon() {
    return (
        <svg className={ITEM_ICON_CLASS} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="M9 4 L5 4 L5 20 L9 20" />
            <path d="M13 12 L20 12 M20 12 L16.5 8.5 M20 12 L16.5 15.5" />
        </svg>
    );
}

const NAV_ITEM_CLASS =
    'flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13.5px] font-medium no-underline hover:no-underline';

export function AppLayout({ children }: AppLayoutProps) {
    const { props, url } = usePage<AppLayoutPageProps>();
    const { auth, impersonation, flash } = props;
    const pathname = url.split('?')[0] ?? url;

    const canAccessAdminPanel = auth.user?.is_super_admin === true;
    const { t } = useTranslations();

    const [open, setOpen] = useState(false);
    const closeMenu = () => setOpen(false);

    const items = [
        { href: app.dashboard.url(), pattern: '/app/dashboard', label: t('nav_dashboard'), icon: <DashboardIcon /> },
        { href: app.places.index.url(), pattern: '/app/places*', label: t('nav_places'), icon: <PlacesIcon /> },
        { href: app.devices.index.url(), pattern: '/app/devices*', label: t('nav_devices'), icon: <DevicesIcon /> },
        {
            href: app.bookings.index.url(),
            pattern: '/app/bookings*',
            exclude: '/app/bookings/integrations*',
            label: t('nav_bookings'),
            icon: <BookingsIcon />,
        },
        { href: app.accessCodes.index.url(), pattern: '/app/access-codes*', label: t('nav_access_codes'), icon: <AccessCodesIcon /> },
        {
            href: app.bookings.integrations.index.url(),
            pattern: '/app/bookings/integrations*',
            label: t('nav_bookings_integrations'),
            icon: <IntegrationsIcon />,
        },
    ];

    const activeItem = items.find((item) => isNavLinkActive(pathname, item.pattern, item.exclude));
    const crumb = activeItem?.label ?? t('nav_dashboard');
    const adminUrl = '/admin';

    return (
        <div className="min-h-screen bg-neutral-100">
            {/* Mobile: hamburger + section title + sair */}
            <div className="sticky top-0 z-20 flex h-14 w-full items-center gap-3 bg-[#0B1220] px-3.5 lg:hidden">
                <button
                    type="button"
                    onClick={() => setOpen((value) => !value)}
                    aria-expanded={open}
                    aria-label={open ? t('nav_close_menu') : t('nav_open_menu')}
                    className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-white/10 text-white focus:outline-none focus:ring-2 focus:ring-primary-300"
                >
                    <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                        <line x1="4" y1="7" x2="20" y2="7" />
                        <line x1="4" y1="12" x2="20" y2="12" />
                        <line x1="4" y1="17" x2="20" y2="17" />
                    </svg>
                </button>
                <span className="min-w-0 flex-1 truncate text-[15px] font-bold text-white">{crumb}</span>
                <Link
                    href={logout.url()}
                    method="post"
                    as="button"
                    aria-label={t('nav_logout')}
                    className="flex h-8 w-8 flex-shrink-0 cursor-pointer items-center justify-center rounded-lg border-0 bg-white/10 text-white"
                >
                    <LogoutIcon />
                </Link>
            </div>

            <div className="flex min-h-[calc(100vh-56px)] lg:min-h-screen">
                {open && <div className="fixed inset-0 z-30 bg-black/50 lg:hidden" onClick={closeMenu} aria-hidden="true" />}

                <aside
                    className={cn(
                        'fixed inset-y-0 left-0 z-40 w-64 -translate-x-full bg-[#0B1220] transition-transform duration-200 ease-out',
                        'lg:static lg:z-auto lg:w-[232px] lg:flex-shrink-0 lg:translate-x-0',
                        open && 'translate-x-0',
                    )}
                >
                    <div className="flex h-full flex-col gap-7 overflow-y-auto p-4">
                        <Link href={app.dashboard.url()} onClick={closeMenu} className="flex items-center px-2 no-underline">
                            <img src="/images/logo/portatec-logo-branco-horizontal.png" alt="Portatec" className="h-6 w-auto" />
                        </Link>

                        <nav className="flex flex-col gap-0.5">
                            {items.map((item) => (
                                <NavLink
                                    key={item.href}
                                    href={item.href}
                                    pattern={item.pattern}
                                    exclude={item.exclude}
                                    onClick={closeMenu}
                                    className={cn(
                                        NAV_ITEM_CLASS,
                                        isNavLinkActive(pathname, item.pattern, item.exclude)
                                            ? 'bg-primary-500/20 text-primary-300'
                                            : 'text-neutral-400 hover:text-neutral-100',
                                    )}
                                >
                                    {item.icon}
                                    {item.label}
                                </NavLink>
                            ))}
                            {canAccessAdminPanel && (
                                <NavLink
                                    href={adminUrl}
                                    pattern="/admin*"
                                    external
                                    className={cn(NAV_ITEM_CLASS, 'text-neutral-400 hover:text-neutral-100')}
                                >
                                    <AdminIcon />
                                    {t('nav_admin')}
                                </NavLink>
                            )}
                        </nav>

                        <Link
                            href={logout.url()}
                            method="post"
                            as="button"
                            className={cn(NAV_ITEM_CLASS, 'mt-auto cursor-pointer border-0 bg-transparent text-left text-neutral-400 hover:text-neutral-100 lg:hidden')}
                        >
                            <LogoutIcon />
                            {t('nav_logout')}
                        </Link>
                    </div>
                </aside>

                <div className="flex min-w-0 flex-1 flex-col">
                    <div className="hidden h-[52px] items-center justify-between border-b border-neutral-200 bg-white px-7 lg:flex">
                        <span className="text-[12.5px] text-neutral-400">
                            Portatec / <b className="font-semibold text-neutral-700">{crumb}</b>
                        </span>
                        <div className="flex items-center gap-4">
                            {canAccessAdminPanel && (
                                <a href={adminUrl} className="text-[13px] font-medium text-neutral-500 no-underline hover:text-neutral-900">
                                    {t('nav_admin')}
                                </a>
                            )}
                            <Link
                                href={logout.url()}
                                method="post"
                                as="button"
                                className="cursor-pointer border-0 bg-transparent p-0 text-[13px] font-semibold text-neutral-500 hover:text-neutral-900"
                            >
                                {t('nav_logout')}
                            </Link>
                        </div>
                    </div>

                    <main className="flex-1 p-4 lg:p-7">
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
                </div>
            </div>
        </div>
    );
}

export default AppLayout;
