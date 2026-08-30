import { Link, usePage } from '@inertiajs/react';
import type { ComponentProps, ReactNode } from 'react';

import { cn } from '@/lib/utils';

/**
 * Converts a route-like glob pattern (e.g. `/app/places*`) into a matcher
 * against the current pathname, mirroring Laravel's `request()->routeIs()`
 * wildcard semantics used by the original Blade `x-nav-link` component.
 */
function matchesPattern(pathname: string, pattern: string): boolean {
    const escaped = pattern
        .split('*')
        .map((segment) => segment.replace(/[.+?^${}()|[\]\\]/g, '\\$&'))
        .join('.*');

    return new RegExp(`^${escaped}$`).test(pathname);
}

export interface NavLinkProps extends Omit<ComponentProps<typeof Link>, 'href' | 'children'> {
    href: string;
    /** Glob pattern (e.g. `/app/places*`) matched against the current pathname to decide the active state. */
    pattern: string;
    /** Use the mobile dropdown spacing variant. */
    mobile?: boolean;
    children: ReactNode;
}

export function NavLink({ href, pattern, mobile = false, className, children, ...props }: NavLinkProps) {
    const { url } = usePage();
    const pathname = url.split('?')[0] ?? url;
    const isActive = matchesPattern(pathname, pattern);

    return (
        <Link
            href={href}
            className={cn(
                mobile && 'py-2',
                isActive ? 'font-semibold text-primary-700 no-underline' : 'text-neutral-700 no-underline hover:text-primary-700',
                className,
            )}
            {...props}
        >
            {children}
        </Link>
    );
}
