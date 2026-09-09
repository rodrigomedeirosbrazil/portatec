import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEventHandler } from 'react';

import { destroy, store } from '@/actions/App/Http/Controllers/App/PlaceMemberController';
import { show } from '@/actions/App/Http/Controllers/App/PlaceController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { FormField } from '@/components/form-field';
import { Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import places from '@/routes/app/places';
import type { Place, PlaceUser } from '@/types';

interface MembersPageProps {
    place: Place;
    placeUsers: PlaceUser[];
    placeRoles: Record<string, string>;
    [key: string]: unknown;
}

interface AddMemberForm {
    email: string;
    role: string;
    label: string;
}

export default function Members({ place, placeUsers, placeRoles }: MembersPageProps) {
    const { t } = useTranslations();
    const { props } = usePage<{ errors: Record<string, string> }>();
    const memberError = props.errors.member;

    const [memberToRemove, setMemberToRemove] = useState<PlaceUser | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm<AddMemberForm>({
        email: '',
        role: 'host',
        label: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(store.url(place.id), {
            preserveScroll: true,
            onSuccess: () => reset('email', 'role', 'label'),
        });
    };

    function confirmRemove() {
        if (!memberToRemove) {
            return;
        }
        router.delete(destroy.url({ place: place.id, placeUser: memberToRemove.id }), {
            preserveScroll: true,
            onFinish: () => setMemberToRemove(null),
        });
    }

    return (
        <AppLayout
            breadcrumbs={[
                { label: t('nav_places'), href: places.index.url() },
                { label: place.name, href: places.show.url({ place: place.id }) },
                { label: t('members') },
            ]}
        >
            <Head title={`${t('manage_members')} – ${place.name}`} />
            <Page>
                <PageHeader title={`${t('manage_members')} – ${place.name}`} backHref={show.url(place.id)} />

                {memberError ? (
                    <div className="rounded-lg border border-destructive/30 bg-destructive/10 p-3 text-destructive">{memberError}</div>
                ) : null}

                <div className="rounded-[10px] border border-border bg-card p-3.5">
                    <h2 className="mt-0 mb-3">{t('members')}</h2>
                    <ul className="m-0 list-none space-y-0 p-0">
                        {placeUsers.length === 0 ? (
                            <li className="text-muted-foreground">{t('member_no_members')}</li>
                        ) : (
                            placeUsers.map((placeUser) => (
                                <li
                                    key={placeUser.id}
                                    className="flex items-center justify-between gap-2 border-b border-border py-2 last:border-b-0"
                                >
                                    <div>
                                        <strong>{placeUser.user?.name}</strong>{' '}
                                        <span className="text-muted-foreground">({placeUser.user?.email})</span> —{' '}
                                        {placeRoles[placeUser.role] ?? placeUser.role}
                                        {placeUser.label ? <> — {placeUser.label}</> : null}
                                    </div>
                                    <Button type="button" variant="outline" size="sm" onClick={() => setMemberToRemove(placeUser)}>
                                        {t('member_remove')}
                                    </Button>
                                </li>
                            ))
                        )}
                    </ul>
                </div>

                <div className="rounded-[10px] border border-border bg-card p-3.5">
                    <h2 className="mt-0 mb-3">{t('add_member')}</h2>
                    <form onSubmit={submit} className="space-y-3">
                        <FormField htmlFor="memberEmail" label={t('user_email_label')} error={errors.email}>
                            <Input
                                id="memberEmail"
                                type="email"
                                autoComplete="off"
                                placeholder={t('user_email_placeholder')}
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                            />
                        </FormField>

                        <FormField htmlFor="addRole" label={t('role')}>
                            <Select value={data.role} onValueChange={(value) => setData('role', value)}>
                                <SelectTrigger id="addRole" className="w-full">
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

                        <FormField htmlFor="addLabel" label={t('label')}>
                            <Input
                                id="addLabel"
                                type="text"
                                maxLength={255}
                                value={data.label}
                                onChange={(e) => setData('label', e.target.value)}
                            />
                        </FormField>

                        <Button type="submit" disabled={processing || data.email === ''}>
                            {t('member_add_submit')}
                        </Button>
                    </form>
                </div>

                <ConfirmDialog
                    open={memberToRemove !== null}
                    onOpenChange={(nextOpen) => {
                        if (!nextOpen) {
                            setMemberToRemove(null);
                        }
                    }}
                    title={t('member_remove_confirm_title')}
                    description={t('member_remove_confirm_description')}
                    onConfirm={confirmRemove}
                />
            </Page>
        </AppLayout>
    );
}
