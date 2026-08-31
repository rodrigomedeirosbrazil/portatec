import * as React from 'react';
import { router } from '@inertiajs/react';

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { SearchInput } from '@/components/search-input';
import { PlaceSelect } from '@/components/place-select';
import { cn } from '@/lib/utils';
import { useTranslations } from '@/hooks/use-translations';
import type { PlaceOption } from '@/types';

export interface SelectOption {
    value: string;
    label: string;
}

export type FilterFieldConfig =
    | { type: 'search'; key: string; label?: string; placeholder?: string }
    | { type: 'date'; key: string; label?: string }
    | { type: 'select'; key: string; label?: string; placeholder?: string; options: SelectOption[] }
    | {
          type: 'place';
          key: string;
          label?: string;
          places: PlaceOption[];
          includeEmpty?: boolean;
          emptyOptionLabel?: string;
          includeUnassigned?: boolean;
          unassignedOptionLabel?: string;
      };

export interface FilterBarProps {
    /** URL da página atual (sem query string), para onde as visitas parciais são enviadas. */
    url: string;
    fields: FilterFieldConfig[];
    /** Valores atuais de cada filtro, indexados por `key`. Ausente ou string vazia = sem filtro. */
    values: Record<string, string>;
    /** Tempo de debounce, em ms, para os campos de busca (`type: 'search'`). Padrão: 300. */
    debounceMs?: number;
    /** Exibe o botão de limpar filtros quando há algum filtro ativo. Padrão: true. */
    showClear?: boolean;
    className?: string;
    /** Classes do grid interno (colunas responsivas). Padrão: 1 coluna, 2 em `sm`. */
    gridClassName?: string;
    /**
     * Controla se chaves de filtro com valor vazio são enviadas explicitamente na
     * query string. Padrão: false (comportamento retrocompatível: apenas chaves
     * com valor não vazio são enviadas).
     *
     * Quando `true`, o backend passa a distinguir três estados por chave:
     * - chave ausente da query string: aplica o valor padrão do backend;
     * - chave presente e vazia (`''`): sem filtro nesse campo;
     * - chave presente com valor: filtra por esse valor.
     */
    sendEmptyValues?: boolean;
}

const SELECT_EMPTY_SENTINEL = '__all__';

/**
 * Monta o objeto de parâmetros de query a partir dos campos declarados e dos
 * valores atuais. Itera sobre `fields` (não sobre `Object.entries(values)`)
 * para que o conjunto de chaves enviadas seja exatamente o conjunto declarado.
 *
 * - `sendEmptyValues === false` (padrão): apenas chaves com valor não vazio
 *   entram no objeto — comportamento idêntico ao histórico, exigido para
 *   retrocompatibilidade com access-codes, devices, places e integrations.
 * - `sendEmptyValues === true`: todas as chaves declaradas entram, as vazias
 *   como string vazia (`''`).
 */
export function buildFilterParams(
    fields: FilterFieldConfig[],
    values: Record<string, string>,
    sendEmptyValues: boolean,
): Record<string, string> {
    const params: Record<string, string> = {};

    for (const field of fields) {
        const value = values[field.key] ?? '';

        if (value !== '') {
            params[field.key] = value;
            continue;
        }

        if (sendEmptyValues) {
            params[field.key] = '';
        }
    }

    return params;
}

/**
 * Busca com debounce e selects sincronizados com a query string via visita
 * parcial do Inertia (`preserveState` + `preserveScroll` + `replace`).
 *
 * Substitui o padrão de `updatedPlaceId()` / `updatedStatus()` etc. dos
 * componentes Livewire de índice, que faziam redirect de página inteira a
 * cada troca de filtro.
 */
export function FilterBar({
    url,
    fields,
    values,
    debounceMs = 300,
    showClear = true,
    className,
    gridClassName,
    sendEmptyValues = false,
}: FilterBarProps) {
    const { t } = useTranslations();
    const [localValues, setLocalValues] = React.useState<Record<string, string>>(values);
    const debounceTimers = React.useRef<Record<string, ReturnType<typeof setTimeout>>>({});

    // Mantém o estado local sincronizado quando o servidor devolve props
    // atualizadas (ex.: navegação por paginação ou botão voltar do navegador).
    React.useEffect(() => {
        setLocalValues(values);
    }, [values]);

    function visit(nextValues: Record<string, string>) {
        const params = buildFilterParams(fields, nextValues, sendEmptyValues);

        router.get(url, params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function setValue(key: string, value: string, { debounce = false }: { debounce?: boolean } = {}) {
        const next = { ...localValues, [key]: value };
        setLocalValues(next);

        if (!debounce) {
            visit(next);
            return;
        }

        if (debounceTimers.current[key]) {
            clearTimeout(debounceTimers.current[key]);
        }
        debounceTimers.current[key] = setTimeout(() => visit(next), debounceMs);
    }

    React.useEffect(() => {
        const timers = debounceTimers.current;
        return () => {
            Object.values(timers).forEach((timer) => clearTimeout(timer));
        };
    }, []);

    const hasActiveFilter = fields.some((field) => (localValues[field.key] ?? '') !== '');

    function clearFilters() {
        Object.values(debounceTimers.current).forEach((timer) => clearTimeout(timer));
        debounceTimers.current = {};

        const cleared: Record<string, string> = {};
        for (const field of fields) {
            cleared[field.key] = '';
        }
        setLocalValues(cleared);
        visit(cleared);
    }

    return (
        <div className={cn('mb-4 rounded-[10px] border border-border bg-card p-3.5', className)}>
            <div className={cn('grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2', gridClassName)}>
                {fields.map((field) => (
                    <div key={field.key} className="min-w-0">
                        <FilterField
                            field={field}
                            value={localValues[field.key] ?? ''}
                            onChangeImmediate={(value) => setValue(field.key, value)}
                            onChangeDebounced={(value) => setValue(field.key, value, { debounce: true })}
                        />
                    </div>
                ))}
            </div>

            {showClear && hasActiveFilter ? (
                <div className="mt-3">
                    <Button type="button" variant="outline" size="sm" onClick={clearFilters}>
                        {t('clear_filters')}
                    </Button>
                </div>
            ) : null}
        </div>
    );
}

interface FilterFieldProps {
    field: FilterFieldConfig;
    value: string;
    onChangeImmediate: (value: string) => void;
    onChangeDebounced: (value: string) => void;
}

function FilterField({ field, value, onChangeImmediate, onChangeDebounced }: FilterFieldProps) {
    const { t } = useTranslations();

    if (field.type === 'search') {
        return (
            <SearchInput
                id={field.key}
                label={field.label ?? t('search_label')}
                placeholder={field.placeholder}
                value={value}
                onChange={onChangeDebounced}
            />
        );
    }

    if (field.type === 'place') {
        return (
            <PlaceSelect
                id={field.key}
                label={field.label}
                value={value}
                onChange={onChangeImmediate}
                places={field.places}
                includeEmpty={field.includeEmpty}
                emptyOptionLabel={field.emptyOptionLabel}
                includeUnassigned={field.includeUnassigned}
                unassignedOptionLabel={field.unassignedOptionLabel}
            />
        );
    }

    if (field.type === 'date') {
        return (
            <div>
                {field.label ? (
                    <Label htmlFor={field.key} className="mb-2 block font-semibold">
                        {field.label}
                    </Label>
                ) : null}
                <Input
                    id={field.key}
                    type="date"
                    value={value}
                    onChange={(event) => onChangeImmediate(event.target.value)}
                    className="h-auto w-full min-w-0 p-2"
                />
            </div>
        );
    }

    // field.type === 'select'
    const selectValue = value === '' ? SELECT_EMPTY_SENTINEL : value;

    return (
        <div>
            {field.label ? (
                <Label htmlFor={field.key} className="mb-2 block font-semibold">
                    {field.label}
                </Label>
            ) : null}
            <Select
                value={selectValue}
                onValueChange={(next) => onChangeImmediate(next === SELECT_EMPTY_SENTINEL ? '' : next)}
            >
                <SelectTrigger id={field.key} className="h-auto w-full min-w-0 p-2">
                    <SelectValue placeholder={field.placeholder} />
                </SelectTrigger>
                <SelectContent>
                    {field.options.map((option) => (
                        <SelectItem key={option.value === '' ? SELECT_EMPTY_SENTINEL : option.value} value={option.value === '' ? SELECT_EMPTY_SENTINEL : option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
