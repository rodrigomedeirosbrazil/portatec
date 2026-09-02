import { Link } from '@inertiajs/react';

import { logout } from '@/routes';
import { useTranslations } from '@/hooks/use-translations';

export interface UserMenuProps {
    name: string;
    email: string;
    isSuperAdmin: boolean;
    /** Fecha a gaveta no mobile ao navegar. */
    onNavigate?: () => void;
}

const LINK_CLASS =
    'block rounded-lg px-2.5 py-1.5 text-[13px] font-medium text-neutral-400 no-underline hover:text-neutral-100';

export function UserMenu({ name, email, isSuperAdmin, onNavigate }: UserMenuProps) {
    const { t } = useTranslations();

    return (
        <div className="mt-auto border-t border-white/10 pt-3">
            <div className="px-2.5 pb-2">
                <p className="m-0 truncate text-[13px] font-semibold text-neutral-100">{name}</p>
                <p className="m-0 truncate text-[11.5px] text-neutral-500">{email}</p>
            </div>

            {isSuperAdmin ? (
                <a href="/admin" className={LINK_CLASS}>
                    Admin
                </a>
            ) : null}

            <Link
                href={logout.url()}
                method="post"
                as="button"
                onClick={onNavigate}
                className={`${LINK_CLASS} w-full cursor-pointer border-0 bg-transparent text-left`}
            >
                {t('nav_logout')}
            </Link>
        </div>
    );
}
