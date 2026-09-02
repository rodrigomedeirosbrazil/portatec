import { router } from '@inertiajs/react';

import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslations } from '@/hooks/use-translations';
import app from '@/routes/app';

const ALL = '__all__';

export interface CurrentPlaceSelectProps {
    places: { id: number; name: string }[];
    currentPlace: { id: number; name: string } | null;
    className?: string;
}

export function CurrentPlaceSelect({ places, currentPlace, className }: CurrentPlaceSelectProps) {
    const { t } = useTranslations();

    if (places.length === 0) {
        return null;
    }

    function change(value: string) {
        router.post(
            app.currentPlace.update.url(),
            { place_id: value === ALL ? '' : value },
            { preserveScroll: true },
        );
    }

    return (
        <Select value={currentPlace ? String(currentPlace.id) : ALL} onValueChange={change}>
            <SelectTrigger className={className} aria-label={t('place_select_label')}>
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={ALL}>{t('place_select_all')}</SelectItem>
                {places.map((place) => (
                    <SelectItem key={place.id} value={String(place.id)}>
                        {place.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
