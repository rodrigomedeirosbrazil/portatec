import { describe, expect, it } from 'vitest';

import { formatRelative } from '../format-relative';

const NOW = Date.parse('2026-10-07T22:00:00Z');

describe('formatRelative', () => {
    it('uses the largest unit that fits', () => {
        expect(formatRelative('2026-10-07T21:55:00Z', NOW)).toBe('há 5 minutos');
        expect(formatRelative('2026-10-07T19:00:00Z', NOW)).toBe('há 3 horas');
        expect(formatRelative('2026-10-05T22:00:00Z', NOW)).toBe('anteontem');
    });

    it('falls back to seconds', () => {
        expect(formatRelative('2026-10-07T21:59:30Z', NOW)).toBe('há 30 segundos');
    });
});
