import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

/**
 * Guarda do ambiente: se alguém remover o jsdom ou o setup do Vitest, esta é a
 * primeira coisa a quebrar, com mensagem clara — em vez de cada teste de
 * componente falhar com "document is not defined".
 */
describe('ambiente de teste de componente', () => {
    it('renderiza JSX num DOM', () => {
        render(<p>ok</p>);

        expect(screen.getByText('ok')).toBeInTheDocument();
    });
});
