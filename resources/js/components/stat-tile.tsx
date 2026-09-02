import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

export type StatTileTone = 'default' | 'error';

export interface StatTileProps {
    /** Rótulo em caixa alta, acima do número. */
    label: string;
    /** Valor já formatado (ex.: "3" ou "2/5"). */
    value: ReactNode;
    /** Quando presente, o tile inteiro vira link para a lista já filtrada. */
    href?: string;
    /** `error` pinta a faixa lateral e o número de vermelho. */
    tone?: StatTileTone;
    className?: string;
}

const BASE_CLASS =
    'relative block overflow-hidden rounded-lg border border-neutral-200 bg-white py-3.5 pr-4 pl-[18px] no-underline';

export function StatTile({ label, value, href, tone = 'default', className }: StatTileProps) {
    const content = (
        <>
            <span
                className={cn('absolute inset-y-0 left-0 w-[3px]', tone === 'error' ? 'bg-error-500' : 'bg-primary-500')}
                aria-hidden="true"
            />
            <p className="m-0 text-[11px] font-bold tracking-wide text-neutral-400 uppercase">{label}</p>
            <p
                className={cn(
                    'm-0 mt-2 font-mono text-2xl font-bold tabular-nums',
                    tone === 'error' ? 'text-error-700' : 'text-neutral-900',
                )}
            >
                {value}
            </p>
        </>
    );

    if (href === undefined) {
        return <div className={cn(BASE_CLASS, className)}>{content}</div>;
    }

    return (
        <Link href={href} className={cn(BASE_CLASS, 'hover:border-primary-300', className)}>
            {content}
        </Link>
    );
}
