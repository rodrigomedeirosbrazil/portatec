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
    /**
     * Destino FORA do app Inertia (hoje, o painel Filament em `/admin`).
     *
     * O `<Link>` do Inertia intercepta o clique e faz uma requisicao XHR esperando
     * uma resposta Inertia de volta. Uma rota que nao e Inertia responde HTML sem o
     * cabecalho `x-inertia`, e o Inertia entao despeja esse HTML num modal de
     * depuracao (iframe sobre fundo escuro) em vez de navegar. Com `external`, sai
     * uma ancora comum e o navegador navega de verdade.
     */
    external?: boolean;
    children: ReactNode;
}

export function NavLink({ href, pattern, mobile = false, external = false, className, children, ...props }: NavLinkProps) {
    const { url } = usePage();
    const pathname = url.split('?')[0] ?? url;
    const isActive = matchesPattern(pathname, pattern);

    const classes = cn(
        mobile && 'py-2',
        isActive ? 'font-semibold text-primary-700 no-underline' : 'text-neutral-700 no-underline hover:text-primary-700',
        className,
    );

    if (external) {
        return (
            <a href={href} className={classes}>
                {children}
            </a>
        );
    }

    return (
        <Link href={href} className={classes} {...props}>
            {children}
        </Link>
    );
}
