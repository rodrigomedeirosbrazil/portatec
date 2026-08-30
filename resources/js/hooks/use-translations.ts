import { usePage } from '@inertiajs/react';

/**
 * Shape of the `translations` shared prop, as sent by
 * `App\Http\Middleware\HandleInertiaRequests` (`trans('app')`).
 *
 * The dictionary is arbitrarily nested (e.g. `place_roles.admin`).
 */
export type TranslationDictionary = {
    [key: string]: string | TranslationDictionary;
};

interface TranslationsPageProps extends Record<string, unknown> {
    translations: TranslationDictionary;
}

/** Placeholder substitutions, Laravel-style (`:name`). */
export type TranslationReplacements = Record<string, string | number>;

function resolve(dictionary: TranslationDictionary, key: string): string | TranslationDictionary | undefined {
    return key.split('.').reduce<string | TranslationDictionary | undefined>((value, segment) => {
        if (value !== null && typeof value === 'object' && segment in value) {
            return value[segment];
        }

        return undefined;
    }, dictionary);
}

function applyReplacements(text: string, replacements?: TranslationReplacements): string {
    if (!replacements) {
        return text;
    }

    return Object.entries(replacements).reduce(
        (result, [name, value]) => result.replaceAll(`:${name}`, String(value)),
        text,
    );
}

/**
 * Reads the `translations` shared prop and exposes a dotted-key translation
 * function, mirroring Laravel's `__('app.<key>')` on the client.
 *
 * Falls back to the key itself when the translation is missing, and supports
 * `:placeholder` substitution the same way Laravel does.
 */
export function useTranslations() {
    const { props } = usePage<TranslationsPageProps>();
    const dictionary = props.translations ?? {};

    function t(key: string, replacements?: TranslationReplacements): string {
        const value = resolve(dictionary, key);

        if (typeof value !== 'string') {
            return key;
        }

        return applyReplacements(value, replacements);
    }

    return { t };
}
