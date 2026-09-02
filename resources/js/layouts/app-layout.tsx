import { Link, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';

import app from '@/routes/app';
import { Breadcrumbs, type Crumb } from '@/components/breadcrumbs';
import { NavLink, isNavLinkActive } from '@/components/nav-link';
import { UserMenu } from '@/components/user-menu';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';

interface AppLayoutPageProps {
    auth: {
        user: { is_super_admin: boolean; name: string; email: string } | null;
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
    /**
     * Trilha da página. Sem ela, o layout cai no rótulo da seção ativa — o
     * comportamento anterior — para que a adoção seja incremental e nenhuma
     * tela quebre enquanto a onda 3 não passa por todas.
     */
    breadcrumbs?: Crumb[];
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

const NAV_ITEM_CLASS =
    'flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-[13.5px] font-medium no-underline hover:no-underline';

export function AppLayout({ children, breadcrumbs }: AppLayoutProps) {
    const { props, url } = usePage<AppLayoutPageProps>();
    const { auth, impersonation, flash } = props;
    const pathname = url.split('?')[0] ?? url;

    const canAccessAdminPanel = auth.user?.is_super_admin === true;
    const { t } = useTranslations();

    const [open, setOpen] = useState(false);
    const closeMenu = () => setOpen(false);

    const groups = [
        {
            label: t('nav_group_operation'),
            items: [
                { href: app.dashboard.url(), pattern: '/app/dashboard', label: t('nav_dashboard'), icon: <DashboardIcon /> },
                { href: app.bookings.index.url(), pattern: '/app/bookings*', label: t('nav_bookings'), icon: <BookingsIcon /> },
                { href: app.accessCodes.index.url(), pattern: '/app/access-codes*', label: t('nav_access_codes'), icon: <AccessCodesIcon /> },
            ],
        },
        {
            label: t('nav_group_setup'),
            items: [
                { href: app.places.index.url(), pattern: '/app/places*', label: t('nav_places'), icon: <PlacesIcon /> },
                { href: app.devices.index.url(), pattern: '/app/devices*', label: t('nav_devices'), icon: <DevicesIcon /> },
            ],
        },
    ];

    const allItems = groups.flatMap((group) => group.items);

    const activeItem = allItems.find((item) => isNavLinkActive(pathname, item.pattern));
    const crumb = activeItem?.label ?? t('nav_dashboard');

    const trail: Crumb[] = breadcrumbs ?? [{ label: crumb }];
    const currentLabel = trail[trail.length - 1]?.label ?? crumb;
    const parent = trail.length > 1 ? trail[trail.length - 2] : undefined;

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
                {parent?.href ? (
                    <Link
                        href={parent.href}
                        aria-label={parent.label}
                        className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg text-white no-underline"
                    >
                        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                            <path d="M15 5 L8 12 L15 19" />
                        </svg>
                    </Link>
                ) : null}
                <span className="min-w-0 flex-1 truncate text-[15px] font-bold text-white">{currentLabel}</span>
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
                            <img src="/images/logo/portatec-logo-horizontal-transparente.png" alt="Portatec" className="h-6 w-auto" />
                        </Link>

                        <nav className="flex flex-col gap-5">
                            {groups.map((group) => (
                                <div key={group.label} className="flex flex-col gap-0.5">
                                    <span className="px-2.5 pb-1 text-[10.5px] font-bold tracking-wider text-neutral-500 uppercase">
                                        {group.label}
                                    </span>
                                    {group.items.map((item) => (
                                        <NavLink
                                            key={item.href}
                                            href={item.href}
                                            pattern={item.pattern}
                                            onClick={closeMenu}
                                            className={cn(
                                                NAV_ITEM_CLASS,
                                                isNavLinkActive(pathname, item.pattern)
                                                    ? 'bg-primary-500/20 text-primary-300'
                                                    : 'text-neutral-400 hover:text-neutral-100',
                                            )}
                                        >
                                            {item.icon}
                                            {item.label}
                                        </NavLink>
                                    ))}
                                </div>
                            ))}
                        </nav>

                        <UserMenu
                            name={auth.user?.name ?? ''}
                            email={auth.user?.email ?? ''}
                            isSuperAdmin={canAccessAdminPanel}
                            onNavigate={closeMenu}
                        />
                    </div>
                </aside>

                <div className="flex min-w-0 flex-1 flex-col">
                    <div className="hidden h-[52px] items-center justify-between border-b border-neutral-200 bg-white px-7 lg:flex">
                        <Breadcrumbs items={[{ label: 'Portatec', href: app.dashboard.url() }, ...trail]} />
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
