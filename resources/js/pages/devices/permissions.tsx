import { Head, router, useForm } from '@inertiajs/react';
import { useState, type FormEventHandler } from 'react';

import { destroy, index, store } from '@/actions/App/Http/Controllers/App/DevicePermissionController';
import { store as transfer } from '@/actions/App/Http/Controllers/App/DeviceTransferController';
import { show } from '@/actions/App/Http/Controllers/App/DeviceController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { FormField } from '@/components/form-field';
import { Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import devices from '@/routes/app/devices';

interface PermissionUser {
    id: number;
    name: string;
    email: string;
}

interface Grantee {
    id: number;
    user: PermissionUser | null;
}

interface DevicePermissionsProps {
    device: { id: number; name: string };
    admin: PermissionUser | null;
    grantees: Grantee[];
    [key: string]: unknown;
}

interface EmailForm {
    email: string;
}

export default function DevicePermissions({ device, admin, grantees }: DevicePermissionsProps) {
    const { t } = useTranslations();

    const [granteeToRevoke, setGranteeToRevoke] = useState<Grantee | null>(null);
    const [transferConfirmOpen, setTransferConfirmOpen] = useState(false);

    const grantForm = useForm<EmailForm>({ email: '' });
    const transferForm = useForm<EmailForm>({ email: '' });

    const submitGrant: FormEventHandler = (event) => {
        event.preventDefault();
        grantForm.post(store.url({ device: device.id }), {
            preserveScroll: true,
            onSuccess: () => grantForm.reset('email'),
        });
    };

    function confirmRevoke() {
        if (!granteeToRevoke) {
            return;
        }

        router.delete(destroy.url({ device: device.id, deviceUser: granteeToRevoke.id }), {
            preserveScroll: true,
            onFinish: () => setGranteeToRevoke(null),
        });
    }

    // A transferência não tem volta pela interface, então o e-mail é validado
    // pelo servidor só depois do "confirmar" — o diálogo mostra o que foi
    // digitado, e é o servidor que diz se aquela conta existe.
    function confirmTransfer() {
        transferForm.post(transfer.url({ device: device.id }), {
            preserveScroll: true,
            onSuccess: () => transferForm.reset('email'),
            onFinish: () => setTransferConfirmOpen(false),
        });
    }

    return (
        <AppLayout
            breadcrumbs={[
                { label: t('nav_devices'), href: devices.index.url() },
                { label: device.name, href: devices.show.url({ device: device.id }) },
                { label: t('device_permissions_title') },
            ]}
        >
            <Head title={`${t('device_permissions_title')} – ${device.name}`} />

            <Page>
                <PageHeader
                    title={`${t('device_permissions_title')} – ${device.name}`}
                    backHref={show.url({ device: device.id })}
                />

                <div className="rounded-[10px] border border-border bg-card p-3.5">
                    <h2 className="mt-0 mb-3">{t('device_permissions_heading')}</h2>
                    <ul className="m-0 list-none space-y-0 p-0">
                        {grantees.length === 0 ? (
                            <li className="text-muted-foreground">{t('device_permissions_empty')}</li>
                        ) : (
                            grantees.map((grantee) => (
                                <li
                                    key={grantee.id}
                                    className="flex items-center justify-between gap-2 border-b border-border py-2 last:border-b-0"
                                >
                                    <div>
                                        <strong>{grantee.user?.name}</strong>{' '}
                                        <span className="text-muted-foreground">({grantee.user?.email})</span>
                                    </div>
                                    <Button type="button" variant="outline" size="sm" onClick={() => setGranteeToRevoke(grantee)}>
                                        {t('device_permission_revoke')}
                                    </Button>
                                </li>
                            ))
                        )}
                    </ul>
                </div>

                <div className="rounded-[10px] border border-border bg-card p-3.5">
                    <h2 className="mt-0 mb-3">{t('device_permission_grant')}</h2>
                    <form onSubmit={submitGrant} className="space-y-3">
                        <FormField htmlFor="grantEmail" label={t('user_email_label')} error={grantForm.errors.email}>
                            <Input
                                id="grantEmail"
                                type="email"
                                autoComplete="off"
                                placeholder={t('user_email_placeholder')}
                                value={grantForm.data.email}
                                onChange={(event) => grantForm.setData('email', event.target.value)}
                            />
                        </FormField>

                        <Button type="submit" disabled={grantForm.processing || grantForm.data.email === ''}>
                            {t('device_permission_grant')}
                        </Button>
                    </form>
                </div>

                <div className="rounded-[10px] border border-border bg-card p-3.5">
                    <h2 className="mt-0 mb-3">{t('device_admin_heading')}</h2>

                    {admin ? (
                        <p className="mt-0 mb-3">
                            <strong>{admin.name}</strong> <span className="text-muted-foreground">({admin.email})</span>
                        </p>
                    ) : (
                        <p className="mt-0 mb-3 text-muted-foreground">{t('device_admin_none')}</p>
                    )}

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            setTransferConfirmOpen(true);
                        }}
                        className="space-y-3"
                    >
                        <FormField htmlFor="transferEmail" label={t('user_email_label')} error={transferForm.errors.email}>
                            <Input
                                id="transferEmail"
                                type="email"
                                autoComplete="off"
                                placeholder={t('user_email_placeholder')}
                                value={transferForm.data.email}
                                onChange={(event) => transferForm.setData('email', event.target.value)}
                            />
                        </FormField>

                        <Button type="submit" variant="outline" disabled={transferForm.processing || transferForm.data.email === ''}>
                            {t('device_transfer')}
                        </Button>
                    </form>
                </div>

                <ConfirmDialog
                    open={granteeToRevoke !== null}
                    onOpenChange={(nextOpen) => {
                        if (!nextOpen) {
                            setGranteeToRevoke(null);
                        }
                    }}
                    title={t('device_permission_revoke')}
                    description={t('device_permission_revoke_confirm', { name: granteeToRevoke?.user?.name ?? '' })}
                    onConfirm={confirmRevoke}
                />

                <ConfirmDialog
                    open={transferConfirmOpen}
                    onOpenChange={setTransferConfirmOpen}
                    title={t('device_transfer')}
                    description={t('device_transfer_confirm', {
                        name: transferForm.data.email,
                        email: transferForm.data.email,
                    })}
                    onConfirm={confirmTransfer}
                />
            </Page>
        </AppLayout>
    );
}
