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

/**
 * Decide se o item do menu deve aparecer como ativo.
 *
 * `pattern` aceita lista porque um item de menu pode viver em mais de um lugar
 * da URL: "Controle" acende tanto em `/app/control` (a lista) quanto em
 * `/app/places/{id}/control` (o painel de um local), que sao a mesma secao para
 * quem navega, ainda que rotas diferentes.
 *
 * `exclude` existe porque padroes se aninham: `/app/places*` engloba
 * `/app/places/{id}/control`, e sem exclusao "Locais" e "Controle" acendem
 * juntos. Mesma classe de problema que o Blade original ja tinha, onde
 * `routeIs('app.bookings.*')` casava com `app.bookings.integrations.index`.
 */
export function isNavLinkActive(pathname: string, pattern: string | string[], exclude?: string | string[]): boolean {
    const patterns = Array.isArray(pattern) ? pattern : [pattern];

    if (!patterns.some((candidate) => matchesPattern(pathname, candidate))) {
        return false;
    }

    const excluded = exclude === undefined ? [] : Array.isArray(exclude) ? exclude : [exclude];

    return !excluded.some((excludedPattern) => matchesPattern(pathname, excludedPattern));
}

// `pattern` tambem e atributo HTML (de `<input>`), tipado como `string`, e vem
// herdado por `Link`. Sem omitir, o nosso `string | string[]` conflita com ele.
export interface NavLinkProps extends Omit<ComponentProps<typeof Link>, 'href' | 'children' | 'pattern'> {
    href: string;
    /** Glob pattern — ou lista deles — casado contra o pathname atual para decidir o estado ativo. */
    pattern: string | string[];
    /** Use the mobile dropdown spacing variant. */
    mobile?: boolean;
    /** Padrao (ou padroes) que, se casarem, impedem o estado ativo mesmo com `pattern` casando. */
    exclude?: string | string[];
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

export function NavLink({ href, pattern, mobile = false, external = false, exclude, className, children, ...props }: NavLinkProps) {
    const { url } = usePage();
    const pathname = url.split('?')[0] ?? url;
    const isActive = isNavLinkActive(pathname, pattern, exclude);

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
