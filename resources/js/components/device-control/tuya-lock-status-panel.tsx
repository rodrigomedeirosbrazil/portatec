import { StatusBadge, type StatusBadgeVariant } from '@/components/status-badge';
import { useTranslations } from '@/hooks/use-translations';
import { formatRelative } from '@/lib/format-relative';
import { cn } from '@/lib/utils';
import type { TuyaLockStatus } from '@/types';

/** Abaixo disso a bateria aparece em destaque. */
const LOW_BATTERY_THRESHOLD = 20;

interface TuyaLockStatusPanelProps {
    status: TuyaLockStatus | null;
}

/** Trancada/destrancada, bateria e alerta de uma fechadura Tuya. Cada linha some quando o dado é desconhecido. */
export function TuyaLockStatusPanel({ status }: TuyaLockStatusPanelProps) {
    const { t } = useTranslations();

    const locked = status?.locked ?? null;
    const variant: StatusBadgeVariant = locked === true ? 'success' : locked === false ? 'warning' : 'neutral';
    const label = locked === true ? t('tuya_lock_locked') : locked === false ? t('tuya_lock_unlocked') : t('tuya_lock_unknown');

    return (
        <div className="space-y-1.5">
            <StatusBadge variant={variant}>{label}</StatusBadge>
            {status?.battery != null ? (
                <p
                    className={cn(
                        'm-0',
                        status.battery < LOW_BATTERY_THRESHOLD ? 'font-medium text-destructive' : 'text-neutral-500',
                    )}
                >
                    {t('tuya_lock_battery', { level: status.battery })}
                </p>
            ) : null}
            {status?.alert ? <p className="m-0 font-medium text-destructive">{t(`tuya_lock_alerts.${status.alert}`)}</p> : null}
            {status?.updated_at ? (
                <p className="m-0 text-sm text-neutral-500">{t('tuya_lock_updated', { when: formatRelative(status.updated_at) })}</p>
            ) : null}
        </div>
    );
}
