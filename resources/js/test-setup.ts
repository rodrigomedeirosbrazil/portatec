import '@testing-library/jest-dom/vitest';

import { cleanup } from '@testing-library/react';
import { afterEach, vi } from 'vitest';

import { getTestPageProps, resetTestPageProps } from './test-utils';

// Problema 1 — o `@testing-library/react` só registra seu próprio
// `afterEach(cleanup)` se `afterEach` já existir como global no momento em
// que o pacote é importado (ver `@testing-library/react/dist/index.js`).
// Como `vitest.config.ts` não usa `globals: true`, isso nunca dispara e o DOM
// se acumularia entre os `it()` de um mesmo arquivo. Registramos aqui, uma
// única vez, para todo teste de componente.
afterEach(() => {
    cleanup();
    resetTestPageProps();
});

// Problema 2 — `usePage()` do Inertia lança fora de um `<App>` Inertia real,
// e nenhum teste de componente monta um. Mockamos só o `usePage`, preservando
// todo o resto do módulo — o `Link`, em particular, precisa continuar
// funcionando de verdade (ver `stat-tile.test.tsx`).
//
// Atenção ao hoisting: `vi.mock` é elevado para o topo do arquivo, antes de
// qualquer import — inclusive o de `./test-utils` acima. Por isso o factory
// abaixo não fecha sobre uma variável de módulo local; `getTestPageProps` só
// é *chamado* dentro da função que o mock devolve, e essa chamada só
// acontece quando um teste de fato importa `@inertiajs/react` e invoca
// `usePage()` — nesse ponto este arquivo de setup já terminou de rodar (é um
// `setupFiles` do Vitest, executado por inteiro antes de qualquer teste), e
// o import de `./test-utils` já foi resolvido. O store em si vive em
// `globalThis` (ver `test-utils.ts`), o que também elimina qualquer
// dependência de ordem de avaliação entre os dois módulos.
vi.mock('@inertiajs/react', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@inertiajs/react')>();

    return {
        ...actual,
        usePage: () => ({
            component: 'test',
            props: getTestPageProps(),
            url: '/',
            version: null,
            clearHistory: false,
            encryptHistory: false,
        }),
    };
});
