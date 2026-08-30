import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useTranslations } from '@/hooks/use-translations';

export interface ConfirmDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    /** Defaults to the translated "Confirmar". */
    confirmLabel?: string;
    /** Defaults to the translated "Cancelar". */
    cancelLabel?: string;
    /** Renders the confirm button with the destructive variant. Defaults to `true` — this dialog exists mainly to confirm deletions. */
    destructive?: boolean;
    onConfirm: () => void;
    confirmDisabled?: boolean;
}

/**
 * Confirmation dialog for destructive actions (deleting a place, a device, a
 * member...), built on shadcn's `alert-dialog`.
 */
export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel,
    cancelLabel,
    destructive = true,
    onConfirm,
    confirmDisabled,
}: ConfirmDialogProps) {
    const { t } = useTranslations();

    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    {description ? <AlertDialogDescription>{description}</AlertDialogDescription> : null}
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>{cancelLabel ?? t('cancel')}</AlertDialogCancel>
                    <AlertDialogAction
                        variant={destructive ? 'destructive' : 'default'}
                        disabled={confirmDisabled}
                        onClick={onConfirm}
                    >
                        {confirmLabel ?? t('confirm')}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
