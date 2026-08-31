import * as React from 'react';
import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

import { cn } from '@/lib/utils';
import { useTranslations } from '@/hooks/use-translations';
import type { Paginated } from '@/types';

export interface PaginationProps {
    /** Lista paginada como `Resource::collection($paginator)` a entrega. */
    paginator: Pick<Paginated<unknown>, 'meta'>;
    /** Mostra o resumo "Mostrando X a Y de Z resultados" acima dos controles. Padrão: true. */
    showSummary?: boolean;
    className?: string;
}

function visit(url: string) {
    router.get(
        url,
        {},
        {
            preserveState: true,
            // Sem `preserveScroll`: os controles ficam no fim da lista, então
            // preservar a posição deixa o leitor parado no rodapé olhando o fim
            // da página nova. O padrão do Inertia volta ao topo, que é o começo
            // do conteúdo que ele acabou de pedir.
        },
    );
}

/**
 * Porte de `resources/views/vendor/pagination/tailwind.blade.php`, navegando
 * com visitas parciais do Inertia (`preserveState`) em vez de recarregar a
 * página inteira.
 */
export function Pagination({ paginator, showSummary = true, className }: PaginationProps) {
    const { t } = useTranslations();
    // Os links por página vivem em `meta.links`; o `links` do nível raiz é
    // apenas {first,last,prev,next} e não serve para montar os botões.
    const { links, from, to, total } = paginator.meta;

    if (links.length <= 3) {
        // Laravel só emite "anterior" + páginas + "próxima": com uma página
        // só, isso equivale a 3 links, nenhum deles útil de mostrar.
        return null;
    }

    const previous = links[0];
    const next = links[links.length - 1];
    const pages = links.slice(1, -1);

    return (
        <div className={className}>
            {showSummary && total > 0 ? (
                <p className="mb-3 text-muted-foreground">
                    {t('pagination_showing')} <span className="font-medium text-foreground">{from}</span> {t('pagination_to')}{' '}
                    <span className="font-medium text-foreground">{to}</span> {t('pagination_of')}{' '}
                    <span className="font-medium text-foreground">{total}</span> {t('pagination_results')}
                </p>
            ) : null}

            <nav role="navigation" aria-label={t('pagination_navigation')} className="flex flex-wrap items-center gap-1">
                <PaginationLink link={previous} onNavigate={visit} ariaLabel={t('pagination_previous')}>
                    <ChevronLeft aria-hidden="true" className="h-4 w-4" />
                </PaginationLink>

                {pages.map((link, index) =>
                    link.url === null && !link.active ? (
                        <span
                            key={`gap-${index}`}
                            aria-hidden="true"
                            className="inline-flex h-8 items-center rounded-lg px-2.5 text-sm text-muted-foreground"
                        >
                            {link.label}
                        </span>
                    ) : (
                        <PaginationLink
                            key={link.label}
                            link={link}
                            onNavigate={visit}
                            ariaLabel={link.active ? undefined : t('pagination_go_to_page', { page: link.label })}
                            active={link.active}
                        >
                            {link.label}
                        </PaginationLink>
                    ),
                )}

                <PaginationLink link={next} onNavigate={visit} ariaLabel={t('pagination_next')}>
                    <ChevronRight aria-hidden="true" className="h-4 w-4" />
                </PaginationLink>
            </nav>
        </div>
    );
}

interface PaginationLinkProps {
    link: { url: string | null; label: string; active: boolean };
    onNavigate: (url: string) => void;
    ariaLabel?: string;
    active?: boolean;
    children: React.ReactNode;
}

function PaginationLink({ link, onNavigate, ariaLabel, active, children }: PaginationLinkProps) {
    if (link.url === null) {
        return (
            <span
                aria-disabled="true"
                aria-label={ariaLabel}
                className="inline-flex h-8 min-w-8 cursor-default items-center justify-center rounded-lg px-2.5 text-sm text-muted-foreground/50"
            >
                {children}
            </span>
        );
    }

    if (active || link.active) {
        return (
            <span
                aria-current="page"
                className="inline-flex h-8 min-w-8 items-center justify-center rounded-lg bg-primary px-2.5 text-sm font-medium text-primary-foreground"
            >
                {children}
            </span>
        );
    }

    return (
        <a
            href={link.url}
            aria-label={ariaLabel}
            onClick={(event) => {
                event.preventDefault();
                onNavigate(link.url as string);
            }}
            className={cn(
                'inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-2.5 text-sm text-foreground no-underline hover:bg-muted',
            )}
        >
            {children}
        </a>
    );
}
