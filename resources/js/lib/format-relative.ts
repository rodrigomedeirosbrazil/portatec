const UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
    ['day', 86_400],
    ['hour', 3_600],
    ['minute', 60],
];

/** "há 5 minutos", "anteontem" — a partir de um ISO 8601. */
export function formatRelative(isoString: string, now: number = Date.now()): string {
    const seconds = Math.round((Date.parse(isoString) - now) / 1000);
    const formatter = new Intl.RelativeTimeFormat('pt-BR', { numeric: 'auto' });

    for (const [unit, size] of UNITS) {
        if (Math.abs(seconds) >= size) {
            return formatter.format(Math.round(seconds / size), unit);
        }
    }

    return formatter.format(seconds, 'second');
}
