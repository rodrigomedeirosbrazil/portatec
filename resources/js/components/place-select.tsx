import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { useTranslations } from '@/hooks/use-translations';
import type { PlaceOption } from '@/types';

/** Valor especial usado para representar "dispositivos sem local". */
export const UNASSIGNED_PLACE_VALUE = 'unassigned';
/** Valor especial usado para representar "todos os locais" (sem filtro). */
export const EMPTY_PLACE_VALUE = '';

export interface PlaceSelectProps {
    places: PlaceOption[];
    /** Local selecionado. String vazia representa "nenhum selecionado". */
    value: string;
    onChange: (value: string) => void;
    id?: string;
    /** Rótulo exibido acima do select. */
    label?: string;
    required?: boolean;
    /** Inclui a opção vazia (ex.: "Todos", usada em filtros). */
    includeEmpty?: boolean;
    emptyOptionLabel?: string;
    /** Inclui a opção especial "unassigned" (usada no filtro de dispositivos). */
    includeUnassigned?: boolean;
    unassignedOptionLabel?: string;
    /** Mensagem de erro de validação (ex.: `errors.place_id` do `useForm`). */
    error?: string;
    className?: string;
    disabled?: boolean;
}

/**
 * Porte de `resources/views/components/place-select.blade.php`: select de
 * locais com opções especiais "todos" e "sem local".
 */
export function PlaceSelect({
    places,
    value,
    onChange,
    id = 'placeId',
    label,
    required = false,
    includeEmpty = false,
    emptyOptionLabel,
    includeUnassigned = false,
    unassignedOptionLabel,
    error,
    className,
    disabled,
}: PlaceSelectProps) {
    const { t } = useTranslations();

    // O Radix Select não aceita item com value="", então usamos um sentinel
    // interno só para a opção "todos" e traduzimos de volta no onChange.
    const emptySentinel = '__all__';
    const selectValue = value === EMPTY_PLACE_VALUE ? emptySentinel : value;

    return (
        <div>
            {label ? (
                <Label htmlFor={id} className="mb-2 block font-semibold">
                    {label}
                </Label>
            ) : null}
            <Select
                value={selectValue}
                onValueChange={(next) => onChange(next === emptySentinel ? EMPTY_PLACE_VALUE : next)}
                disabled={disabled}
                required={required}
            >
                <SelectTrigger id={id} className={cn('h-auto w-full max-w-[360px] min-w-0 p-2', className)}>
                    <SelectValue placeholder={t('select_place')} />
                </SelectTrigger>
                <SelectContent>
                    {includeEmpty ? <SelectItem value={emptySentinel}>{emptyOptionLabel ?? t('all_places')}</SelectItem> : null}
                    {includeUnassigned ? (
                        <SelectItem value={UNASSIGNED_PLACE_VALUE}>{unassignedOptionLabel ?? t('unassigned_place')}</SelectItem>
                    ) : null}
                    {places.map((place) => (
                        <SelectItem key={place.id} value={String(place.id)}>
                            {place.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {error ? <p className="mt-1 text-destructive">{error}</p> : null}
        </div>
    );
}
