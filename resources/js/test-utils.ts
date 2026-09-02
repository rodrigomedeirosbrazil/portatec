/**
 * Utilitário de teste para popular `usePage().props` do Inertia.
 *
 * Qualquer componente que use `useTranslations()` (ou, no futuro, qualquer
 * outro hook baseado em `usePage()`) lê deste store — ver o mock de
 * `@inertiajs/react` registrado em `resources/js/test-setup.ts`.
 *
 * Sem chamar `setTestPageProps`, o padrão já é `{ translations: {} }`, que é
 * exatamente o suficiente para `useTranslations().t('chave')` cair no
 * fallback do hook (devolver a própria chave).
 *
 * O store fica em `globalThis` para que o mock de `usePage` em
 * `test-setup.ts` possa lê-lo sem fechar sobre nenhuma variável importada
 * (ver o comentário sobre hoisting de `vi.mock` lá). O reset entre testes é
 * feito automaticamente em `test-setup.ts` — nenhum teste precisa chamar
 * `resetTestPageProps` diretamente.
 */

export type TestPageProps = Record<string, unknown>;

declare global {
    // eslint-disable-next-line no-var -- `var` é a única forma de declarar um global aumentável.
    var __testPageProps: TestPageProps | undefined;
}

const DEFAULT_TEST_PAGE_PROPS: TestPageProps = {
    translations: {},
};

/**
 * Define as props de página que os componentes sob teste vão enxergar ao
 * chamar `usePage().props` — por exemplo `setTestPageProps({ translations: {
 * nav_logout: 'Sair' } })`.
 *
 * As props informadas são mescladas sobre o padrão (`{ translations: {} }`),
 * então chamar só com o que importa para o teste é suficiente.
 */
export function setTestPageProps(props: TestPageProps): void {
    globalThis.__testPageProps = { ...DEFAULT_TEST_PAGE_PROPS, ...props };
}

/** Restaura o store para o padrão. Chamado automaticamente após cada teste em `test-setup.ts`. */
export function resetTestPageProps(): void {
    globalThis.__testPageProps = { ...DEFAULT_TEST_PAGE_PROPS };
}

/** Lido pelo mock de `usePage` registrado em `test-setup.ts`. */
export function getTestPageProps(): TestPageProps {
    return globalThis.__testPageProps ?? { ...DEFAULT_TEST_PAGE_PROPS };
}
