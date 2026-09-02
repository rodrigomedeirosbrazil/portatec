import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import { UserMenu } from '@/components/user-menu';

/**
 * O item "Admin" some para quem não é super admin — a mesma condição que o
 * `User::canAccessPanel` aplica no backend. Mostrar um link que devolve 403 é
 * pior do que não mostrar link nenhum.
 */
describe('UserMenu', () => {
    it('mostra nome e e-mail do usuário', () => {
        render(<UserMenu name="Rodrigo" email="rodrigo@exemplo.com" isSuperAdmin={false} />);

        expect(screen.getByText('Rodrigo')).toBeTruthy();
        expect(screen.getByText('rodrigo@exemplo.com')).toBeTruthy();
    });

    it('não oferece Admin para usuário comum', () => {
        render(<UserMenu name="Rodrigo" email="rodrigo@exemplo.com" isSuperAdmin={false} />);

        expect(screen.queryByText('Admin')).toBeNull();
    });

    it('oferece Admin para super admin', () => {
        render(<UserMenu name="Rodrigo" email="rodrigo@exemplo.com" isSuperAdmin />);

        expect(screen.getByText('Admin')).toBeTruthy();
    });
});
