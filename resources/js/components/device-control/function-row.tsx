import { Check, Loader2 } from 'lucide-react';

import { useTranslations } from '@/hooks/use-translations';
import type { ControlStatus, NormalizedDeviceStatus } from '@/hooks/device-commands-reducer';

export type ControllableFunctionType = 'button' | 'switch';

export interface FunctionRowProps {
    /** Rótulo do tipo de função já traduzido pelo servidor (ex.: "Botão", "Interruptor"). */
    typeLabel: string;
    type: ControllableFunctionType;
    pin: string;
    /** Estado atual da linha (`idle` | `sending` | `sent` | `acked`), vindo de `useDeviceCommands`. */
    status: ControlStatus;
    disabled: boolean;
    /**
     * Status do sensor do dispositivo (não desta função em si) — mesmo valor
     * é repetido em todas as linhas do dispositivo, espelhando
     * `function-row.blade.php` hoje.
     */
    functionStatus?: NormalizedDeviceStatus | null;
    onTrigger: () => void;
}

/**
 * Uma linha de função controlável (botão ou interruptor) dentro do card de um
 * dispositivo, na tela de controle. Porta `function-row.blade.php` 1:1: o
 * mesmo rótulo "{tipo} (PIN {pin})", o badge de status do sensor do
 * dispositivo (quando existe) e os quatro estados idle/sending/sent/acked do
 * botão.
 */
export function FunctionRow({ typeLabel, type, pin, status, disabled, functionStatus, onTrigger }: FunctionRowProps) {
    const { t } = useTranslations();

    const statusLabel = functionStatus
        ? functionStatus.kind === 'raw'
            ? functionStatus.value
            : t(`device_statuses.${functionStatus.kind}`)
        : null;

    const idleLabel = type === 'button' ? t('device_control_send') : t('device_control_toggle');

    return (
        <div className="mb-2.5 rounded-lg border border-neutral-200 p-3">
            <p className="m-0 mb-2.5 text-neutral-700">{t('device_control_function_label', { type: typeLabel, pin })}</p>

            {statusLabel !== null ? (
                <p className="m-0 mb-2.5 text-sm text-neutral-600">
                    <span className="rounded-full bg-neutral-100 px-2 py-0.5 font-medium">{statusLabel}</span>
                </p>
            ) : null}

            <button
                type="button"
                onClick={onTrigger}
                disabled={disabled}
                className="inline-flex cursor-pointer items-center gap-2 rounded-lg border-0 bg-primary-500 px-3 py-2 text-white hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {status === 'idle' ? <span>{idleLabel}</span> : null}
                {status === 'sending' ? (
                    <span className="inline-flex items-center gap-2">
                        <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                        {t('device_control_sending')}
                    </span>
                ) : null}
                {status === 'sent' ? (
                    <span className="inline-flex items-center gap-2">
                        <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                        {t('device_control_waiting_ack')}
                    </span>
                ) : null}
                {status === 'acked' ? (
                    <span className="inline-flex items-center gap-2">
                        <Check className="h-4 w-4" aria-hidden="true" />
                        {t('device_control_acked')}
                    </span>
                ) : null}
            </button>
        </div>
    );
}
