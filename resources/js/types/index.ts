/**
 * Tipos compartilhados entre páginas e componentes de dados (DataTable,
 * Pagination, FilterBar, PlaceSelect) e as props globais expostas pelo
 * HandleInertiaRequests (auth, impersonation, flash, translations).
 */

export * from './models';

/** Um item de link do paginator do Laravel (->paginate()->withQueryString()). */
export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

/**
 * Formato produzido por `Resource::collection($paginator)`, que é como os
 * controllers entregam listas paginadas.
 *
 * ATENÇÃO: é diferente do paginator puro (`->paginate()->toArray()`). Aqui
 * `links` é um objeto com first/last/prev/next, e o array de links por página
 * fica em `meta.links`, junto de from/to/total. Confundir os dois quebra a
 * paginação apenas no navegador.
 */
export interface Paginated<T> {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        to: number | null;
        last_page: number;
        per_page: number;
        total: number;
        path: string;
        links: PaginationLink[];
    };
}

/** Local (Place) mínimo necessário para popular selects e listas. */
export interface PlaceOption {
    id: number;
    name: string;
}

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    /** Autorizado no painel Filament (`/admin`). Falso em sessão assumida. */
    is_super_admin: boolean;
}

export interface Auth {
    user: AuthUser | null;
}

export interface Impersonation {
    active: boolean;
}

export interface Flash {
    status: string | null;
}

/**
 * Dicionário de traduções (trans('app')). Alguns valores são listas
 * aninhadas (ex.: place_roles), por isso o tipo é recursivo.
 */
export type TranslationValue = string | { [key: string]: TranslationValue };
export type Translations = Record<string, TranslationValue>;

/** Props compartilhadas por toda página Inertia via HandleInertiaRequests. */
export interface SharedPageProps extends Record<string, unknown> {
    auth: Auth;
    impersonation: Impersonation;
    flash: Flash;
    translations: Translations;
}
