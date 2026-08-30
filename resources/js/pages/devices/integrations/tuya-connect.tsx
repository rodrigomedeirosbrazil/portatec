import { Head, Link, router } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useState } from 'react';
import type { FormEventHandler } from 'react';

import { Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import devices from '@/routes/app/devices';

type TuyaWizardStep = 'form' | 'qr' | 'devices' | 'done';

interface TuyaDeviceMeta {
    id: string;
    name: string;
    category: string;
    categoryLabel: string;
    online: boolean;
    productId: string | null;
    productName: string | null;
    icon: string | null;
    status: Array<{ code: string; value: unknown }>;
    selected: boolean;
}

interface TuyaConnectPageProps {
    step: TuyaWizardStep;
    qrUrl: string | null;
    qrExpiresAt: number | null;
    devices: TuyaDeviceMeta[];
    errorMessage: string | null;
    [key: string]: unknown;
}

interface TuyaQrGenerateResponse {
    step: 'qr';
    qrUrl: string;
    qrExpiresAt: number;
}

interface TuyaQrPollResponse {
    status: 'pending' | 'confirmed' | 'error';
    step?: TuyaWizardStep;
    devices?: TuyaDeviceMeta[];
    errorMessage?: string;
}

/**
 * Wizard de conexão Tuya (4 passos: form -> qr -> devices -> done), porta 1:1
 * de `App\Livewire\Integrations\TuyaConnect` +
 * `resources/views/livewire/integrations/tuya-connect.blade.php`.
 *
 * Segurança: o estado intermediário do wizard (o token de polling do QR, o
 * access/refresh token da Tuya e os metadados dos devices descobertos) vive
 * inteiramente na sessão do servidor — nunca é enviado como prop Inertia nem
 * em resposta JSON. Only the current step, the public `qrUrl`, its expiry,
 * the device list *without secrets* and error messages ever reach the
 * client. See `TuyaQrController` and `TuyaConnectController`.
 */
export default function TuyaConnect(props: TuyaConnectPageProps) {
    const { t } = useTranslations();

    const [step, setStep] = useState<TuyaWizardStep>(props.step);
    const [qrUrl, setQrUrl] = useState(props.qrUrl);
    const [qrExpiresAt, setQrExpiresAt] = useState(props.qrExpiresAt);
    const [devicesFound, setDevicesFound] = useState<TuyaDeviceMeta[]>(props.devices);
    const [errorMessage, setErrorMessage] = useState(props.errorMessage);
    const [userCode, setUserCode] = useState('');
    const [userCodeError, setUserCodeError] = useState<string | undefined>(undefined);
    const [generating, setGenerating] = useState(false);
    const [saving, setSaving] = useState(false);

    // Polling do QR a cada 3s enquanto o passo for 'qr' — equivalente ao
    // `wire:poll.3000ms` do componente Livewire. O intervalo é limpo tanto no
    // unmount quanto na mudança de passo, já que aqui (diferente do
    // Livewire) o polling não morre sozinho junto com o componente.
    useEffect(() => {
        if (step !== 'qr') {
            return;
        }

        const interval = window.setInterval(() => {
            axios
                .get<TuyaQrPollResponse>(devices.integrations.tuya.qr.poll.url())
                .then(({ data }) => {
                    if (data.status === 'confirmed') {
                        setDevicesFound(data.devices ?? []);
                        setStep('devices');

                        return;
                    }

                    if (data.status === 'error') {
                        setErrorMessage(data.errorMessage ?? null);
                        setQrUrl(null);
                        setQrExpiresAt(null);
                        setStep('form');
                    }
                })
                .catch(() => {
                    // Falha transitória de rede: mantém o polling, tenta de novo no próximo tick.
                });
        }, 3000);

        return () => window.clearInterval(interval);
    }, [step]);

    const generateQr: FormEventHandler = (e) => {
        e.preventDefault();
        setGenerating(true);
        setErrorMessage(null);
        setUserCodeError(undefined);

        axios
            .post<TuyaQrGenerateResponse>(devices.integrations.tuya.qr.store.url(), { user_code: userCode })
            .then(({ data }) => {
                setQrUrl(data.qrUrl);
                setQrExpiresAt(data.qrExpiresAt);
                setStep('qr');
            })
            .catch((error: unknown) => {
                if (axios.isAxiosError(error) && error.response) {
                    const data = error.response.data as { errorMessage?: string; errors?: Record<string, string[]> };
                    setUserCodeError(data.errors?.user_code?.[0]);
                    setErrorMessage(data.errorMessage ?? null);
                }
            })
            .finally(() => setGenerating(false));
    };

    const cancelQr = () => {
        axios.delete(devices.integrations.tuya.qr.destroy.url()).finally(() => {
            setStep('form');
            setQrUrl(null);
            setQrExpiresAt(null);
            setDevicesFound([]);
            setErrorMessage(null);
        });
    };

    const toggleDevice = (id: string) => {
        setDevicesFound((prev) => prev.map((device) => (device.id === id ? { ...device, selected: !device.selected } : device)));
    };

    const selectedCount = devicesFound.filter((device) => device.selected).length;

    const saveIntegration = () => {
        setSaving(true);

        router.post(
            devices.integrations.tuya.store.url(),
            { device_ids: devicesFound.filter((device) => device.selected).map((device) => device.id) },
            { onFinish: () => setSaving(false) },
        );
    };

    return (
        <AppLayout>
            <Head title={t('tuya_connect_page_title')} />

            <Page>
                <PageHeader title={t('tuya_connect_page_title')} backHref={devices.integrations.index.url()} />

                {errorMessage ? (
                    <div className="rounded-lg border border-red-300 bg-red-50 px-3 py-2.5 text-red-700">{errorMessage}</div>
                ) : null}

                {step === 'form' ? (
                    <div className="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                        <h2 className="mb-1 text-lg font-semibold">{t('tuya_step1_title')}</h2>
                        <p className="mb-4 text-sm text-neutral-600" dangerouslySetInnerHTML={{ __html: t('tuya_step1_instructions') }} />

                        <form onSubmit={generateQr} className="grid gap-3">
                            <div>
                                <Label htmlFor="userCode" className="text-sm font-medium">
                                    {t('tuya_user_code_label')}
                                </Label>
                                <Input
                                    id="userCode"
                                    type="text"
                                    value={userCode}
                                    onChange={(e) => setUserCode(e.target.value)}
                                    placeholder={t('tuya_user_code_placeholder')}
                                    autoComplete="off"
                                    className="mt-1 font-mono"
                                />
                                {userCodeError ? <p className="mt-1 text-sm text-red-500">{userCodeError}</p> : null}
                            </div>

                            <Button type="submit" disabled={generating} className="w-fit">
                                {generating ? t('tuya_generating') : t('tuya_generate_qr')}
                            </Button>
                        </form>
                    </div>
                ) : null}

                {step === 'qr' && qrUrl ? (
                    <div className="rounded-[10px] border border-neutral-300 bg-white p-3.5 text-center">
                        <h2 className="mb-1 text-lg font-semibold">{t('tuya_step2_title')}</h2>
                        <p className="mb-4 text-sm text-neutral-600">{t('tuya_step2_instructions')}</p>

                        <div className="mx-auto mb-4 flex items-center justify-center">
                            <img
                                src={`https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(qrUrl)}`}
                                alt={t('tuya_qr_alt')}
                                className="rounded-lg border border-neutral-200"
                                width={220}
                                height={220}
                            />
                        </div>

                        <p className="mb-2 font-mono text-xs break-all text-neutral-400">{qrUrl}</p>

                        <div className="mt-4 flex items-center justify-center gap-2 text-sm text-neutral-500">
                            <svg className="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                            </svg>
                            {t('tuya_waiting_confirmation')}
                        </div>

                        {qrExpiresAt ? (
                            <p className="mt-2 text-xs text-neutral-400">
                                {t('tuya_qr_expires_at')} {new Date(qrExpiresAt * 1000).toLocaleTimeString('pt-BR')}
                            </p>
                        ) : null}

                        <Button variant="outline" type="button" onClick={cancelQr} className="mt-4">
                            {t('tuya_cancel')}
                        </Button>
                    </div>
                ) : null}

                {step === 'devices' ? (
                    <div className="rounded-[10px] border border-neutral-300 bg-white p-3.5">
                        <h2 className="mb-1 text-lg font-semibold">{t('tuya_step3_title')}</h2>
                        <p className="mb-4 text-sm text-neutral-600">
                            {t('tuya_devices_found', { count: devicesFound.length })}
                        </p>

                        <div className="mb-4 grid gap-2">
                            {devicesFound.length === 0 ? (
                                <p className="py-4 text-center text-sm text-neutral-500">{t('tuya_no_devices_found')}</p>
                            ) : (
                                devicesFound.map((device) => (
                                    <label
                                        key={device.id}
                                        className={`flex cursor-pointer items-center gap-3 rounded-lg border p-2.5 transition-colors ${
                                            device.selected ? 'border-primary-400 bg-primary-50' : 'border-neutral-200 bg-white hover:bg-neutral-50'
                                        }`}
                                    >
                                        <Checkbox checked={device.selected} onCheckedChange={() => toggleDevice(device.id)} />
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <span className="truncate text-sm font-medium">{device.name}</span>
                                                {device.online ? (
                                                    <span className="text-xs font-medium text-green-600">{t('online')}</span>
                                                ) : (
                                                    <span className="text-xs text-neutral-400">{t('offline')}</span>
                                                )}
                                            </div>
                                            <p className="mt-0.5 text-xs text-neutral-500">{device.categoryLabel}</p>
                                            <p className="mt-0.5 font-mono text-xs text-neutral-400">{device.id}</p>
                                        </div>
                                    </label>
                                ))
                            )}
                        </div>

                        <div className="flex items-center gap-3">
                            <Button type="button" onClick={saveIntegration} disabled={saving || selectedCount === 0}>
                                {saving ? t('tuya_saving') : t('tuya_save_integration')}
                            </Button>
                            <Button variant="outline" type="button" onClick={cancelQr}>
                                {t('tuya_cancel')}
                            </Button>
                        </div>
                    </div>
                ) : null}

                {step === 'done' ? (
                    <div className="rounded-[10px] border border-green-300 bg-green-50 p-5 text-center">
                        <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
                            <svg className="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <h2 className="text-lg font-semibold text-green-800">{t('tuya_connected_title')}</h2>
                        <p className="mt-1 text-sm text-green-700">{t('tuya_devices_imported')}</p>

                        <div className="mt-5 flex justify-center gap-3">
                            <Button asChild>
                                <Link href={devices.index.url()}>{t('tuya_view_devices')}</Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={devices.integrations.index.url()}>{t('tuya_view_integrations')}</Link>
                            </Button>
                        </div>
                    </div>
                ) : null}
            </Page>
        </AppLayout>
    );
}
