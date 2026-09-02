import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { StatTile } from '@/components/stat-tile';

/**
 * O tile é o mesmo bloco visual do dashboard e do detalhe do local. A única
 * diferença de comportamento é ser ou não clicável: sem `href` ele é um bloco
 * inerte; com `href`, um link para a lista já filtrada.
 */
describe('StatTile', () => {
    it('renderiza rótulo e valor', () => {
        render(<StatTile label="Dispositivos" value="3" />);

        expect(screen.getByText('Dispositivos')).toBeTruthy();
        expect(screen.getByText('3')).toBeTruthy();
    });

    it('não é um link quando não recebe href', () => {
        render(<StatTile label="Dispositivos" value="3" />);

        expect(screen.queryByRole('link')).toBeNull();
    });

    it('é um link para o href quando recebe href', () => {
        render(<StatTile label="Reservas" value="7" href="/app/bookings?place_id=1" />);

        expect(screen.getByRole('link').getAttribute('href')).toBe('/app/bookings?place_id=1');
    });

    it('usa a faixa de alerta quando tone é error', () => {
        const { container } = render(<StatTile label="Offline" value="2" tone="error" />);

        expect(container.querySelector('.bg-error-500')).toBeTruthy();
    });
});
