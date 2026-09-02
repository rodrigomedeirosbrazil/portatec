<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Place;
use App\Services\CurrentPlaceService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user !== null ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    // Decide a exibicao do item "Admin" no menu. Em sessao assumida o
                    // usuario efetivo e o cliente, entao a flag e dele - alinhada ao 403
                    // que User::canAccessPanel ja devolve nesse caso.
                    'is_super_admin' => $user->hasRole('super_admin'),
                ] : null,
            ],
            'impersonation' => [
                'active' => $request->session()->has('impersonator_id'),
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
            'translations' => fn () => trans('app'),
            'currentPlace' => function () use ($user): ?array {
                if ($user === null) {
                    return null;
                }

                $place = app(CurrentPlaceService::class)->get($user);

                return $place === null ? null : ['id' => $place->id, 'name' => $place->name];
            },
            'places' => function () use ($user): array {
                if ($user === null) {
                    return [];
                }

                return Place::query()
                    ->whereHas('placeUsers', fn ($query) => $query->where('user_id', $user->id))
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Place $place): array => ['id' => $place->id, 'name' => $place->name])
                    ->all();
            },
        ];
    }
}
