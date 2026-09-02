import { describe, expect, it } from 'vitest';

import { isNavLinkActive } from '@/components/nav-link';

/**
 * O item "Reservas" usa o padrão `/app/bookings*`, que engloba a rota das
 * integrações iCal (`/app/bookings/integrations`). Sem exclusão, os dois itens
 * do menu acendem ao mesmo tempo — defeito que já existia no Blade original,
 * onde `routeIs('app.bookings.*')` também casava com
 * `app.bookings.integrations.index`.
 */
describe('isNavLinkActive', () => {
    it('marca o item quando o padrão casa com a rota atual', () => {
        expect(isNavLinkActive('/app/bookings', '/app/bookings*')).toBe(true);
        expect(isNavLinkActive('/app/bookings/12', '/app/bookings*')).toBe(true);
        expect(isNavLinkActive('/app/places', '/app/places*')).toBe(true);
    });

    it('não marca quando o padrão não casa', () => {
        expect(isNavLinkActive('/app/devices', '/app/bookings*')).toBe(false);
        expect(isNavLinkActive('/app/dashboard', '/app/places*')).toBe(false);
    });

    it('exige rota exata quando o padrão não tem curinga', () => {
        expect(isNavLinkActive('/app/dashboard', '/app/dashboard')).toBe(true);
        expect(isNavLinkActive('/app/dashboard/x', '/app/dashboard')).toBe(false);
    });

    it('não marca Reservas quando a rota é a de integrações iCal', () => {
        expect(
            isNavLinkActive('/app/bookings/integrations', '/app/bookings*', '/app/bookings/integrations*'),
        ).toBe(false);

        expect(
            isNavLinkActive('/app/bookings/integrations/create', '/app/bookings*', '/app/bookings/integrations*'),
        ).toBe(false);
    });

    it('continua marcando Reservas nas demais rotas de reserva', () => {
        expect(isNavLinkActive('/app/bookings', '/app/bookings*', '/app/bookings/integrations*')).toBe(true);
        expect(isNavLinkActive('/app/bookings/create', '/app/bookings*', '/app/bookings/integrations*')).toBe(true);
        expect(isNavLinkActive('/app/bookings/12', '/app/bookings*', '/app/bookings/integrations*')).toBe(true);
    });

    it('aceita mais de um padrão de exclusão', () => {
        expect(isNavLinkActive('/app/bookings/x', '/app/bookings*', ['/app/bookings/y*', '/app/bookings/x*'])).toBe(false);
        expect(isNavLinkActive('/app/bookings/z', '/app/bookings*', ['/app/bookings/y*', '/app/bookings/x*'])).toBe(true);
    });

    /**
     * Com "Integrações iCal" fora da sidebar, o item Reservas acender em
     * /app/bookings/integrations passa a ser o comportamento correto: aquela tela é
     * uma sub-página de Reservas. O `exclude` continua existindo para o dia em que
     * um item de menu voltar a aninhar sob outro.
     */
    it('marca Reservas nas integrações iCal quando não há exclusão', () => {
        expect(isNavLinkActive('/app/bookings/integrations', '/app/bookings*')).toBe(true);
        expect(isNavLinkActive('/app/bookings/integrations/create', '/app/bookings*')).toBe(true);
    });
});

/**
 * "Controle" vive em duas rotas: `/app/control` (a lista) e
 * `/app/places/{id}/control` (o painel de um local). Para quem navega é a mesma
 * seção, então o item precisa acender nas duas — e "Locais" precisa PARAR de
 * acender na segunda, senão dois itens do menu ficam ativos ao mesmo tempo.
 */
describe('estado ativo de Controle e Locais', () => {
    const CONTROL = ['/app/control', '/app/places/*/control'];
    const PLACES = '/app/places*';
    const PLACES_EXCLUDE = '/app/places/*/control';

    it('acende Controle na lista e no painel de um local', () => {
        expect(isNavLinkActive('/app/control', CONTROL)).toBe(true);
        expect(isNavLinkActive('/app/places/2/control', CONTROL)).toBe(true);
    });

    it('nao acende Controle nas demais rotas de local', () => {
        expect(isNavLinkActive('/app/places', CONTROL)).toBe(false);
        expect(isNavLinkActive('/app/places/2', CONTROL)).toBe(false);
        expect(isNavLinkActive('/app/places/2/members', CONTROL)).toBe(false);
    });

    it('nao acende Locais no painel de controle de um local', () => {
        expect(isNavLinkActive('/app/places/2/control', PLACES, PLACES_EXCLUDE)).toBe(false);
    });

    it('continua acendendo Locais nas demais rotas de local', () => {
        expect(isNavLinkActive('/app/places', PLACES, PLACES_EXCLUDE)).toBe(true);
        expect(isNavLinkActive('/app/places/2', PLACES, PLACES_EXCLUDE)).toBe(true);
        expect(isNavLinkActive('/app/places/2/members', PLACES, PLACES_EXCLUDE)).toBe(true);
    });

    it('os dois itens nunca ficam ativos ao mesmo tempo', () => {
        for (const path of ['/app/control', '/app/places', '/app/places/2', '/app/places/2/control', '/app/places/2/members']) {
            const active = [
                isNavLinkActive(path, CONTROL),
                isNavLinkActive(path, PLACES, PLACES_EXCLUDE),
            ].filter(Boolean);

            expect(active.length).toBeLessThanOrEqual(1);
        }
    });
});
