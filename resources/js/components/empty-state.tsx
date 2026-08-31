import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

export interface EmptyStateProps {
    /** Mensagem exibida quando não há itens para mostrar (já traduzida pelo chamador). */
    message: string;
    /** Ação opcional (tipicamente um botão ou link "Novo ..."), já traduzida pelo chamador. */
    action?: ReactNode;
    className?: string;
}

/**
 * Estado vazio padrão para listas e grades sem itens. Agnóstico de domínio:
 * quem consome define a mensagem e a ação.
 */
export function EmptyState({ message, action, className }: EmptyStateProps) {
    return (
        <div className={cn('col-span-full py-8 text-center', className)}>
            <svg
                className="mx-auto mb-3 h-12 w-12 text-neutral-300"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={1.5}
                    d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"
                />
            </svg>
            <p className="mb-3 text-neutral-500">{message}</p>
            {action ? <div>{action}</div> : null}
        </div>
    );
}
