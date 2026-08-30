import type { ReactNode } from 'react';
import { Link, usePage } from '@inertiajs/react';

import { cn } from '@/lib/utils';

interface SharedPageProps {
    translations: Record<string, string>;
    [key: string]: unknown;
}

export interface PageProps {
    /** Conteúdo das seções da página (cabeçalho, cartões, tabelas, etc.). */
    children: ReactNode;
    className?: string;
}

/**
 * Wrapper simples de conteúdo de página, para padronizar o espaçamento
 * vertical entre as seções (cabeçalho, corpo, etc.).
 */
export function Page({ children, className }: PageProps) {
    return <div className={cn('space-y-4', className)}>{children}</div>;
}

export interface PageHeaderProps {
    /** Título principal da página. */
    title: string;
    /** Subtítulo opcional, exibido abaixo do título. */
    subtitle?: string;
    /** URL do Inertia para o link "Voltar" exibido acima do título. */
    backHref?: string;
    /** Ações exibidas à direita do cabeçalho (tipicamente um botão "Novo ..."). */
    actions?: ReactNode;
    className?: string;
}

/**
 * Cabeçalho padrão de página: título, link opcional de voltar, subtítulo
 * opcional e um slot de ações à direita.
 */
export function PageHeader({ title, subtitle, backHref, actions, className }: PageHeaderProps) {
    const { translations } = usePage<SharedPageProps>().props;

    return (
        <div className={cn('flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between', className)}>
            <div>
                {backHref ? (
                    <Link href={backHref} className="text-primary-500 no-underline hover:text-primary-700">
                        &larr; {translations.back}
                    </Link>
                ) : null}
                <h1 className={cn('m-0', backHref ? 'mt-2' : undefined)}>{title}</h1>
                {subtitle ? <p className="mt-1 text-sm text-neutral-500">{subtitle}</p> : null}
            </div>
            {actions ? <div className="flex flex-wrap items-center gap-2">{actions}</div> : null}
        </div>
    );
}
