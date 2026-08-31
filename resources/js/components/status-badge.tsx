import type { ComponentProps, ReactNode } from 'react';

import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type StatusBadgeVariant = 'success' | 'neutral' | 'warning' | 'error';

const variantClasses: Record<StatusBadgeVariant, string> = {
    success: 'border-success-300 bg-success-100 text-success-700',
    neutral: 'border-neutral-300 bg-neutral-100 text-neutral-500',
    warning: 'border-primary-300 bg-primary-100 text-primary-700',
    error: 'border-destructive/40 bg-destructive/10 text-destructive',
};

export interface StatusBadgeProps extends Omit<ComponentProps<typeof Badge>, 'variant'> {
    /** Variante de cor semântica, nunca uma classe crua vinda de fora. */
    variant: StatusBadgeVariant;
    /** Texto já traduzido pelo chamador. */
    children: ReactNode;
}

/**
 * Badge de status reutilizável, agnóstico de domínio: quem consome decide
 * qual variante semântica (sucesso, neutro, alerta, erro) e qual texto usar.
 */
export function StatusBadge({ variant, className, children, ...props }: StatusBadgeProps) {
    return (
        <Badge
            variant="outline"
            className={cn('rounded-full border font-medium', variantClasses[variant], className)}
            {...props}
        >
            {children}
        </Badge>
    );
}
