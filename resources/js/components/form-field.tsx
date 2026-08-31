import type { ReactNode } from 'react';

import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export interface FormFieldProps {
    /** Id of the control this field wraps — used for the label's `htmlFor`. */
    htmlFor: string;
    label: string;
    /** The input/select/textarea/checkbox etc. this field wraps. */
    children: ReactNode;
    /**
     * Validation error for this field, straight from Inertia's `useForm`
     * `errors` object (e.g. `errors.name`). Validation itself stays 100% on
     * the Laravel side (FormRequest) — this only renders what comes back.
     */
    error?: string;
    /** Helper text shown when there is no error. */
    description?: string;
    required?: boolean;
    className?: string;
}

/**
 * Label + control + validation message, wired to Inertia's `useForm` error
 * shape. Domain-agnostic: it renders whatever control is passed as
 * `children` (`<Input>`, `<Select>`, `<Textarea>`, `<Checkbox>`...).
 */
export function FormField({ htmlFor, label, children, error, description, required, className }: FormFieldProps) {
    return (
        <div className={cn('space-y-1.5', className)}>
            <Label htmlFor={htmlFor}>
                {label}
                {required && (
                    <span aria-hidden="true" className="text-destructive">
                        *
                    </span>
                )}
            </Label>
            {children}
            {error ? (
                <p className="text-sm text-destructive">{error}</p>
            ) : description ? (
                <p className="text-sm text-muted-foreground">{description}</p>
            ) : null}
        </div>
    );
}
