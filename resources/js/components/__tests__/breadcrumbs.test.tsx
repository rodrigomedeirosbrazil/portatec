import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';

import { Breadcrumbs } from '@/components/breadcrumbs';

describe('Breadcrumbs', () => {
    // Sem isto o DOM se acumula entre os `it` deste arquivo: o vitest.config.ts
    // não tem `globals: true`, então o `afterEach` automático do
    // @testing-library/react nunca se registra (ele só se ativa se `afterEach`
    // já existir como global). Ver testing-library/react/dist/index.js.
    afterEach(() => {
        cleanup();
    });

    const trail = [
        { label: 'Locais', href: '/app/places' },
        { label: 'Casa Azul', href: '/app/places/1' },
        { label: 'Membros' },
    ];

    it('renderiza todos os itens da trilha', () => {
        render(<Breadcrumbs items={trail} />);

        expect(screen.getByText('Locais')).toBeTruthy();
        expect(screen.getByText('Casa Azul')).toBeTruthy();
        expect(screen.getByText('Membros')).toBeTruthy();
    });

    it('transforma em link todos os itens com href, menos o último', () => {
        render(<Breadcrumbs items={trail} />);

        const links = screen.getAllByRole('link');

        expect(links).toHaveLength(2);
        expect(links[0].getAttribute('href')).toBe('/app/places');
        expect(links[1].getAttribute('href')).toBe('/app/places/1');
    });

    it('nunca transforma o último item em link, mesmo com href', () => {
        render(<Breadcrumbs items={[{ label: 'Locais', href: '/app/places' }]} />);

        expect(screen.queryByRole('link')).toBeNull();
    });

    it('marca o último item como a página atual', () => {
        render(<Breadcrumbs items={trail} />);

        expect(screen.getByText('Membros').getAttribute('aria-current')).toBe('page');
    });
});
