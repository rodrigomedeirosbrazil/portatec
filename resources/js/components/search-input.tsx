import * as React from 'react';
import { Search } from 'lucide-react';

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { useTranslations } from '@/hooks/use-translations';

export interface SearchInputProps extends Omit<React.ComponentProps<'input'>, 'type' | 'onChange'> {
    /** Valor controlado da busca. */
    value: string;
    /** Chamado a cada digitação, sem debounce (o debounce é responsabilidade de quem consome, ex. `<FilterBar>`). */
    onChange: (value: string) => void;
    /** Rótulo exibido acima do campo. Omitido quando `undefined`. */
    label?: string;
    /** Classe aplicada ao wrapper externo. */
    containerClassName?: string;
}

/**
 * Porte de `resources/views/components/search-input.blade.php`: campo de
 * busca com ícone de lupa e rótulo opcional.
 */
export function SearchInput({ value, onChange, label, id = 'search', placeholder, containerClassName, className, ...props }: SearchInputProps) {
    const { t } = useTranslations();

    return (
        <div className={containerClassName}>
            {label ? (
                <Label htmlFor={id} className="mb-2 block font-semibold">
                    {label}
                </Label>
            ) : null}
            <div className="relative">
                <Search
                    aria-hidden="true"
                    className="pointer-events-none absolute top-1/2 left-2.5 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    id={id}
                    type="search"
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    placeholder={placeholder ?? t('search_placeholder')}
                    className={cn('h-auto w-full min-w-0 py-2 pr-3 pl-9', className)}
                    {...props}
                />
            </div>
        </div>
    );
}
