import { describe, expect, it } from 'vitest';

import { buildFilterParams, type FilterFieldConfig } from '@/components/filter-bar';

const searchField = (key: string): FilterFieldConfig => ({ type: 'search', key });
const dateField = (key: string): FilterFieldConfig => ({ type: 'date', key });
const selectField = (key: string): FilterFieldConfig => ({ type: 'select', key, options: [] });

describe('buildFilterParams', () => {
    it('sendEmptyValues=false (padrão): envia apenas chaves com valor não vazio — retrocompatibilidade com access-codes, devices, places e integrations', () => {
        const fields: FilterFieldConfig[] = [searchField('search'), selectField('status'), dateField('date_from')];
        const values = { search: 'joão', status: '', date_from: '' };

        expect(buildFilterParams(fields, values, false)).toEqual({ search: 'joão' });
    });

    it('sendEmptyValues=true: envia todas as chaves declaradas em fields, vazias como string vazia', () => {
        const fields: FilterFieldConfig[] = [searchField('search'), selectField('status'), dateField('date_from')];
        const values = { search: 'joão', status: '', date_from: '' };

        expect(buildFilterParams(fields, values, true)).toEqual({
            search: 'joão',
            status: '',
            date_from: '',
        });
    });

    it('o conjunto de chaves vem de fields, não de values: chave extra em values que não está em fields não aparece no resultado', () => {
        const fields: FilterFieldConfig[] = [searchField('search')];
        const values = { search: 'joão', unexpected_key: 'valor' };

        expect(buildFilterParams(fields, values, false)).toEqual({ search: 'joão' });
        expect(buildFilterParams(fields, values, true)).toEqual({ search: 'joão' });
    });

    it('tela de bookings: os 6 campos reais (place_id, date_from, date_to, status, guest, source)', () => {
        const fields: FilterFieldConfig[] = [
            selectField('place_id'),
            dateField('date_from'),
            dateField('date_to'),
            selectField('status'),
            searchField('guest'),
            selectField('source'),
        ];
        const values = {
            place_id: '3',
            date_from: '',
            date_to: '',
            status: 'confirmed',
            guest: '',
            source: '',
        };

        expect(buildFilterParams(fields, values, false)).toEqual({
            place_id: '3',
            status: 'confirmed',
        });

        expect(buildFilterParams(fields, values, true)).toEqual({
            place_id: '3',
            date_from: '',
            date_to: '',
            status: 'confirmed',
            guest: '',
            source: '',
        });
    });
});
