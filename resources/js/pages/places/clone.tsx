import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';

import { store } from '@/actions/App/Http/Controllers/App/PlaceCloneController';
import { show } from '@/actions/App/Http/Controllers/App/PlaceController';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import { Page, PageHeader } from '@/components/page';
import type { Place } from '@/types';

interface CloneUserOption {
    id: number;
    name: string;
    email: string;
}

interface ClonePlaceProps {
    place: Place;
    suggestedName: string;
    placeRoles: Record<string, string>;
    users: CloneUserOption[];
    [key: string]: unknown;
}

interface AdditionalMemberRow {
    user_id: string;
    role: string;
    label: string;
}

interface ClonePlaceForm {
    name: string;
    additionalMembers: AdditionalMemberRow[];
}

export default function ClonePlace({ place, suggestedName, placeRoles, users }: ClonePlaceProps) {
    const { t } = useTranslations();

    const defaultRole = Object.keys(placeRoles)[0] ?? 'host';

    const { data, setData, post, processing, errors } = useForm<ClonePlaceForm>({
        name: suggestedName,
        additionalMembers: [],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(store.url({ place: place.id }));
    };

    function addRow(): void {
        setData('additionalMembers', [
            ...data.additionalMembers,
            { user_id: '', role: defaultRole, label: '' },
        ]);
    }

    function removeRow(index: number): void {
        setData(
            'additionalMembers',
            data.additionalMembers.filter((_, i) => i !== index),
        );
    }

    function updateRow(index: number, patch: Partial<AdditionalMemberRow>): void {
        setData(
            'additionalMembers',
            data.additionalMembers.map((row, i) => (i === index ? { ...row, ...patch } : row)),
        );
    }

    return (
        <AppLayout>
            <Head title={t('clone_place')} />

            <Page>
                <PageHeader
                    title={`${t('clone_place')}: ${place.name}`}
                    backHref={show.url({ place: place.id })}
                />

                <form onSubmit={submit} className="space-y-6">
                    <div className="rounded-lg border border-neutral-200 bg-white p-3.5">
                        <FormField htmlFor="name" label={t('clone_place_new_name')} error={errors.name} required>
                            <Input
                                id="name"
                                type="text"
                                required
                                maxLength={255}
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                            />
                        </FormField>
                    </div>

                    <div className="rounded-lg border border-neutral-200 bg-white p-3.5">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="mt-0">{t('clone_place_add_people')}</h2>
                            <Button type="button" variant="outline" size="sm" onClick={addRow}>
                                {t('clone_place_add_person')}
                            </Button>
                        </div>
                        <p className="mb-3 text-sm text-neutral-500">{t('clone_place_help')}</p>

                        {data.additionalMembers.map((row, index) => (
                            <div key={index} className="mb-3 flex flex-wrap items-end gap-2 rounded border border-neutral-200 p-2">
                                <div className="min-w-[200px] flex-1">
                                    <FormField
                                        htmlFor={`member-${index}-user`}
                                        label={t('user')}
                                        error={errors[`additionalMembers.${index}.user_id`]}
                                    >
                                        <Select
                                            value={row.user_id}
                                            onValueChange={(value) => updateRow(index, { user_id: value })}
                                        >
                                            <SelectTrigger id={`member-${index}-user`} className="w-full">
                                                <SelectValue placeholder={t('clone_place_select_user')} />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {users.map((user) => (
                                                    <SelectItem key={user.id} value={String(user.id)}>
                                                        {user.name} ({user.email})
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </FormField>
                                </div>
                                <div className="w-32">
                                    <FormField htmlFor={`member-${index}-role`} label={t('role')}>
                                        <Select
                                            value={row.role}
                                            onValueChange={(value) => updateRow(index, { role: value })}
                                        >
                                            <SelectTrigger id={`member-${index}-role`} className="w-full">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Object.entries(placeRoles).map(([value, label]) => (
                                                    <SelectItem key={value} value={value}>
                                                        {label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </FormField>
                                </div>
                                <div className="min-w-[120px] flex-1">
                                    <FormField htmlFor={`member-${index}-label`} label={t('label')}>
                                        <Input
                                            id={`member-${index}-label`}
                                            type="text"
                                            placeholder={t('clone_place_label_placeholder')}
                                            value={row.label}
                                            onChange={(e) => updateRow(index, { label: e.target.value })}
                                        />
                                    </FormField>
                                </div>
                                <Button type="button" variant="destructive" size="sm" onClick={() => removeRow(index)}>
                                    {t('remove')}
                                </Button>
                            </div>
                        ))}
                    </div>

                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing}>
                            {t('clone_place')}
                        </Button>
                        <Link href={show.url({ place: place.id })}>
                            <Button type="button" variant="outline">
                                {t('cancel')}
                            </Button>
                        </Link>
                    </div>
                </form>
            </Page>
        </AppLayout>
    );
}
