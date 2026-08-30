import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { useTranslations } from '@/hooks/use-translations';

interface GuestLayoutPageProps {
    flash: {
        status: string | null;
    };
    errors: Record<string, string>;
    [key: string]: unknown;
}

export interface GuestLayoutProps {
    children: ReactNode;
    /** Overrides the default subtitle shown under the logo. */
    subtitle?: string;
}

export function GuestLayout({ children, subtitle }: GuestLayoutProps) {
    const { props } = usePage<GuestLayoutPageProps>();
    const { flash, errors } = props;
    const { t } = useTranslations();

    const errorMessages = Object.values(errors ?? {});

    return (
        <main className="mx-auto flex min-h-screen w-full max-w-md items-center px-6 py-10">
            <section className="w-full rounded-xl bg-white p-6 shadow-sm ring-1 ring-neutral-300">
                <div className="mb-6 flex justify-center">
                    <img
                        src="/images/logo/portatec-logo-branco.png"
                        alt="Portatec"
                        className="h-28 w-auto max-w-[280px]"
                    />
                </div>
                <p className="mb-6 text-sm text-neutral-500">{subtitle ?? t('guest_default_subtitle')}</p>

                {flash.status && (
                    <div className="mb-4 rounded-md border border-success-300 bg-success-100 px-3 py-2 text-sm text-success-700">
                        {flash.status}
                    </div>
                )}

                {errorMessages.length > 0 && (
                    <div className="mb-4 rounded-md border border-error-300 bg-error-100 px-3 py-2 text-sm text-error-700">
                        <ul className="list-disc pl-4">
                            {errorMessages.map((message) => (
                                <li key={message}>{message}</li>
                            ))}
                        </ul>
                    </div>
                )}

                {children}
            </section>
        </main>
    );
}

export default GuestLayout;
