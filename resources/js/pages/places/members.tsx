import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState, type FormEventHandler } from 'react';

import { destroy, store } from '@/actions/App/Http/Controllers/App/PlaceMemberController';
import searchMembers from '@/actions/App/Http/Controllers/App/PlaceMemberSearchController';
import { show } from '@/actions/App/Http/Controllers/App/PlaceController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { FormField } from '@/components/form-field';
import { Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandItem, CommandList } from '@/components/ui/command';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslations } from '@/hooks/use-translations';
import { AppLayout } from '@/layouts/app-layout';
import type { Place, PlaceUser } from '@/types';

interface MembersPageProps {
    place: Place;
    placeUsers: PlaceUser[];
    placeRoles: Record<string, string>;
    [key: string]: unknown;
}

interface SearchResultUser {
    id: number;
    name: string;
    email: string;
}

interface AddMemberForm {
    user_id: number | null;
    role: string;
    label: string;
}

export default function Members({ place, placeUsers, placeRoles }: MembersPageProps) {
    const { t } = useTranslations();
    const { props } = usePage<{ errors: Record<string, string> }>();
    const memberError = props.errors.member;

    const [selectedUser, setSelectedUser] = useState<SearchResultUser | null>(null);
    const [searchTerm, setSearchTerm] = useState('');
    const [results, setResults] = useState<SearchResultUser[]>([]);
    const [open, setOpen] = useState(false);
    const searchTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const [memberToRemove, setMemberToRemove] = useState<PlaceUser | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm<AddMemberForm>({
        user_id: null,
        role: 'host',
        label: '',
    });

    useEffect(() => {
        if (searchTimer.current) {
            clearTimeout(searchTimer.current);
        }

        if (searchTerm.length < 2) {
            setResults([]);
            return;
        }

        searchTimer.current = setTimeout(() => {
            fetch(searchMembers.url({ place: place.id }, { query: { search: searchTerm } }))
                .then((response) => response.json())
                .then((body: { data: SearchResultUser[] }) => setResults(body.data ?? []))
                .catch(() => setResults([]));
        }, 300);

        return () => {
            if (searchTimer.current) {
                clearTimeout(searchTimer.current);
            }
        };
    }, [searchTerm, place.id]);

    function selectUser(user: SearchResultUser) {
        setSelectedUser(user);
        setData('user_id', user.id);
        setOpen(false);
    }

    function clearSelectedUser() {
        setSelectedUser(null);
        setData('user_id', null);
        setSearchTerm('');
        setResults([]);
    }

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(store.url(place.id), {
            preserveScroll: true,
            onSuccess: () => {
                clearSelectedUser();
                reset('role', 'label');
            },
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
        <AppLayout>
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
                        <FormField htmlFor="userSearch" label={t('member_search_label')} error={errors.user_id}>
                            {selectedUser ? (
                                <div className="flex items-center justify-between gap-2 rounded-lg border border-input bg-muted/40 p-2.5">
                                    <span>
                                        <strong>{selectedUser.name}</strong>{' '}
                                        <span className="text-muted-foreground">({selectedUser.email})</span>
                                    </span>
                                    <button
                                        type="button"
                                        onClick={clearSelectedUser}
                                        className="text-sm text-primary-600 hover:text-primary-700 hover:underline"
                                    >
                                        {t('member_selected_change')}
                                    </button>
                                </div>
                            ) : (
                                <Popover open={open} onOpenChange={setOpen}>
                                    <PopoverTrigger asChild>
                                        <Input
                                            id="userSearch"
                                            type="text"
                                            autoComplete="off"
                                            placeholder={t('member_search_placeholder')}
                                            value={searchTerm}
                                            onChange={(e) => {
                                                setSearchTerm(e.target.value);
                                                setOpen(true);
                                            }}
                                            onFocus={() => setOpen(true)}
                                        />
                                    </PopoverTrigger>
                                    <PopoverContent className="w-(--radix-popper-anchor-width) p-0" align="start" onOpenAutoFocus={(e) => e.preventDefault()}>
                                        <Command shouldFilter={false}>
                                            <CommandList>
                                                {searchTerm.length >= 2 ? (
                                                    <CommandEmpty>{t('member_search_no_results')}</CommandEmpty>
                                                ) : null}
                                                <CommandGroup>
                                                    {results.map((user) => (
                                                        <CommandItem key={user.id} value={String(user.id)} onSelect={() => selectUser(user)}>
                                                            <strong>{user.name}</strong>{' '}
                                                            <span className="text-muted-foreground">({user.email})</span>
                                                        </CommandItem>
                                                    ))}
                                                </CommandGroup>
                                            </CommandList>
                                        </Command>
                                    </PopoverContent>
                                </Popover>
                            )}
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

                        <Button type="submit" disabled={processing || !data.user_id}>
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
