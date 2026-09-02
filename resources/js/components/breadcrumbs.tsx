import { Link } from '@inertiajs/react';
import { Fragment } from 'react';

import { cn } from '@/lib/utils';

export interface Crumb {
    label: string;
    /** Sem href, o item é texto. O último item nunca vira link, tenha href ou não. */
    href?: string;
}

export interface BreadcrumbsProps {
    items: Crumb[];
    className?: string;
}

export function Breadcrumbs({ items, className }: BreadcrumbsProps) {
    return (
        <nav aria-label="breadcrumb" className={cn('text-[12.5px] text-neutral-400', className)}>
            {items.map((item, index) => {
                const isLast = index === items.length - 1;

                return (
                    <Fragment key={`${item.label}-${index}`}>
                        {index > 0 ? <span className="mx-1" aria-hidden="true">/</span> : null}
                        {isLast || item.href === undefined ? (
                            <span
                                aria-current={isLast ? 'page' : undefined}
                                className={cn(isLast && 'font-semibold text-neutral-700')}
                            >
                                {item.label}
                            </span>
                        ) : (
                            <Link href={item.href} className="text-neutral-400 no-underline hover:text-neutral-700">
                                {item.label}
                            </Link>
                        )}
                    </Fragment>
                );
            })}
        </nav>
    );
}
