<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\DeviceBrandEnum;
use App\Enums\DeviceTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Http\Resources\AccessCodeDeviceSyncResource;
use App\Http\Resources\CommandLogResource;
use App\Http\Resources\DeviceResource;
use App\Http\Resources\PlaceResource;
use App\Models\AccessCodeDeviceSync;
use App\Models\CommandLog;
use App\Models\Device;
use App\Models\DeviceFunction;
use App\Models\Place;
use App\Services\Device\DevicePlaceFunctionSyncService;
use App\Services\Tuya\TuyaIntegrationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DeviceController extends Controller
{
    private const PER_PAGE = 20;

    /**
     * Ported 1:1 from `App\Livewire\Devices\Index::render()` /
     * `allowedPlaceIds()`. The rest of the screen (place options, full
     * filter UI) is fleshed out by the parallel implementation phase.
     */
    public function index(Request $request): Response
    {
        $userPlaceIds = Auth::user()->placeUsers()->pluck('place_id');
        $hasDeviceUserTable = Schema::hasTable('device_user');

        $allowedPlaceIds = $this->allowedPlaceIds();
        $places = Place::query()
            ->whereIn('id', $allowedPlaceIds->toArray())
            ->orderBy('name')
            ->get();

        $placeIdParam = $request->query('place_id');
        $placeFilter = $placeIdParam === null || $placeIdParam === '' ? null : (string) $placeIdParam;
        $search = (string) $request->query('search', '');

        $devices = Device::query()
            ->with(['places', 'place'])
            ->withCount('deviceFunctions')
            ->where(function (Builder $query) use ($userPlaceIds, $hasDeviceUserTable): void {
                if ($userPlaceIds->isNotEmpty()) {
                    $query->where(function (Builder $query) use ($userPlaceIds): void {
                        $query->whereHas('places', fn ($q) => $q->whereIn('places.id', $userPlaceIds))
                            ->orWhereIn('place_id', $userPlaceIds);
                    });
                }
                // Dispositivo recém importado ainda não tem local: quem o vê é o dono,
                // pelo vínculo em device_user. Não pode haver ramo sem escopo aqui —
                // ele expõe os dispositivos sem local de todas as contas.
                if ($hasDeviceUserTable) {
                    $query->orWhereHas('deviceUsers', fn ($q) => $q->where('user_id', Auth::id()));
                }
            })
            ->when($placeFilter === 'unassigned', fn (Builder $query) => $query->whereNull('place_id')->whereDoesntHave('places'))
            ->when(
                $placeFilter !== null && $placeFilter !== 'unassigned',
                function (Builder $query) use ($placeFilter): void {
                    $query->where(function (Builder $query) use ($placeFilter): void {
                        $query->whereHas('places', fn ($q) => $q->where('places.id', (int) $placeFilter))
                            ->orWhere('place_id', (int) $placeFilter);
                    });
                }
            )
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.str_replace('%', '\\%', $search).'%';
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('external_device_id', 'like', $term)
                        ->orWhere('brand', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('devices/index', [
            'devices' => DeviceResource::collection($devices),
            'places' => PlaceResource::collection($places),
            'search' => $search,
            'placeId' => $placeFilter,
        ]);
    }

    /**
     * @return Collection<int, int>
     */
    private function allowedPlaceIds(): Collection
    {
        $userPlaceIds = Auth::user()->placeUsers()->pluck('place_id');
        $sharedDevicePlaceIds = collect();
        if (Schema::hasTable('device_user')) {
            $sharedDevicePlaceIds = Device::query()
                ->whereHas('deviceUsers', fn ($q) => $q->where('user_id', Auth::id()))
                ->with('places:id')
                ->get()
                ->flatMap(function (Device $device) {
                    $placeIds = $device->places->pluck('id');
                    if ($device->place_id !== null) {
                        $placeIds->push($device->place_id);
                    }

                    return $placeIds;
                })
                ->unique()
                ->values();
        }

        return $userPlaceIds->merge($sharedDevicePlaceIds)->unique()->filter()->values();
    }

    /**
     * Ported 1:1 from `App\Livewire\Devices\Create::mount()`: pre-selects
     * the place passed in the route (checking the user's link to it) or
     * falls back to the user's first place.
     */
    public function create(Request $request, ?Place $place = null): Response
    {
        $placeIds = [];

        if ($place !== null) {
            abort_unless(
                $place->placeUsers()->where('user_id', Auth::id())->exists(),
                403
            );
            $placeIds = [$place->id];
        } else {
            $defaultPlaceId = Auth::user()->placeUsers()->value('place_id');
            if ($defaultPlaceId !== null) {
                $placeIds = [$defaultPlaceId];
            }
        }

        $places = Place::query()
            ->whereHas('placeUsers', fn (Builder $query) => $query->where('user_id', Auth::id()))
            ->orderBy('name')
            ->get();

        return Inertia::render('devices/create', [
            'places' => PlaceResource::collection($places),
            'placeIds' => $placeIds,
            'brands' => array_column(DeviceBrandEnum::cases(), 'value'),
        ]);
    }

    /**
     * Ported 1:1 from `App\Livewire\Devices\Create::save()`, including the
     * ownership re-check for every `placeIds` entry — without it a device
     * could be attached to a place the user has no access to.
     */
    public function store(StoreDeviceRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $placeIds = collect($validated['placeIds'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $allowedPlaceIds = Auth::user()
            ->placeUsers()
            ->whereIn('place_id', $placeIds)
            ->pluck('place_id')
            ->all();

        abort_unless(count($allowedPlaceIds) === count($placeIds), 403);

        $device = Device::create([
            'place_id' => $placeIds[0] ?? null,
            'name' => $validated['name'],
            'brand' => DeviceBrandEnum::from($validated['brand']),
            'external_device_id' => ($validated['external_device_id'] ?? null) ?: null,
            'default_pin' => ($validated['default_pin'] ?? null) ?: null,
        ]);

        $device->places()->sync($placeIds);

        return redirect()
            ->route('app.devices.show', ['device' => $device->id])
            ->with('status', __('app.device_created'));
    }

    /**
     * Ported 1:1 from `App\Livewire\Devices\Show`: authorization via
     * `DevicePolicy::view()`, Tuya snapshot refresh (best-effort, tuya
     * brand only), and the 20 most recent command logs / access-code
     * device syncs for the device.
     */
    public function show(Request $request, Device $device, TuyaIntegrationService $tuyaIntegrationService): Response
    {
        abort_unless(Auth::user()?->can('view', $device), 403);

        $device->load(['places', 'place', 'deviceFunctions', 'integration']);

        $this->refreshTuyaSnapshot($device, $tuyaIntegrationService);

        $device->refresh();

        $recentCommands = CommandLog::query()
            ->whereHas('deviceFunction', fn (Builder $query) => $query->where('device_id', $device->id))
            ->latest()
            ->limit(20)
            ->get();

        $recentTuyaSyncs = AccessCodeDeviceSync::query()
            ->where('device_id', $device->id)
            ->latest()
            ->limit(20)
            ->get();

        return Inertia::render('devices/show', [
            'device' => new DeviceResource($device),
            'recentCommands' => CommandLogResource::collection($recentCommands),
            'recentTuyaSyncs' => AccessCodeDeviceSyncResource::collection($recentTuyaSyncs),
        ]);
    }

    private function refreshTuyaSnapshot(Device $device, TuyaIntegrationService $tuyaIntegrationService): void
    {
        if ($device->brand?->value !== 'tuya') {
            return;
        }

        try {
            $tuyaIntegrationService->refreshDeviceSnapshot($device);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Ported 1:1 from `App\Livewire\Devices\Edit::mount()`: access is
     * granted either through the user's link to one of the device's places,
     * or — for a device with no place yet — through the direct
     * `device_user` link. `deviceFunctions` falls back to a single empty
     * row when the device has none, matching `Edit::addFunction()`.
     */
    public function edit(Request $request, Device $device): Response
    {
        $device->load(['deviceFunctions', 'places']);

        $devicePlaceIds = $device->places->pluck('id')->all();
        $hasAccess = $devicePlaceIds !== []
            ? Auth::user()->placeUsers()->whereIn('place_id', $devicePlaceIds)->exists()
            : Auth::user()->devices()->where('devices.id', $device->id)->exists();

        abort_unless($hasAccess, 403);

        $placeIds = $devicePlaceIds;
        if ($placeIds === [] && $device->place_id !== null) {
            $placeIds = [$device->place_id];
        }

        $deviceFunctions = $device->deviceFunctions
            ->map(fn (DeviceFunction $function) => [
                'id' => $function->id,
                'type' => $function->type->value,
                'pin' => $function->pin,
            ])
            ->all();

        if ($deviceFunctions === []) {
            $deviceFunctions = [[
                'id' => null,
                'type' => DeviceTypeEnum::Switch->value,
                'pin' => '',
            ]];
        }

        $places = Place::query()
            ->whereHas('placeUsers', fn (Builder $query) => $query->where('user_id', Auth::id()))
            ->orderBy('name')
            ->get();

        return Inertia::render('devices/edit', [
            'device' => new DeviceResource($device),
            'places' => PlaceResource::collection($places),
            'placeIds' => $placeIds,
            'deviceFunctions' => $deviceFunctions,
            'brands' => array_column(DeviceBrandEnum::cases(), 'value'),
            'deviceTypes' => array_column(DeviceTypeEnum::cases(), 'value'),
        ]);
    }

    /**
     * Ported 1:1 from `App\Livewire\Devices\Edit::save()`: same ownership
     * re-check on `placeIds` as `store()`, then reconciles device functions
     * (deletes the ones missing from the payload, updates the ones with an
     * `id` — always scoped to this device — and creates the rest) before
     * delegating the place/place-function pivot sync to
     * `DevicePlaceFunctionSyncService`.
     */
    public function update(UpdateDeviceRequest $request, Device $device, DevicePlaceFunctionSyncService $syncService): RedirectResponse
    {
        // Unlike the Livewire component (whose `save()` runs against an
        // already-`mount()`-checked, signed component snapshot), this route
        // takes `{device}` straight from the URL, so it needs its own
        // access check — the placeIds ownership check below only validates
        // the *target* places, not that the caller may touch this device.
        abort_unless(Auth::user()?->can('update', $device), 403);

        $validated = $request->validated();

        $placeIds = collect($validated['placeIds'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $allowedPlaceIds = Auth::user()
            ->placeUsers()
            ->whereIn('place_id', $placeIds)
            ->pluck('place_id')
            ->all();

        abort_unless(count($allowedPlaceIds) === count($placeIds), 403);

        $device->update([
            'place_id' => $placeIds[0] ?? null,
            'name' => $validated['name'],
            'brand' => DeviceBrandEnum::from($validated['brand']),
            'external_device_id' => ($validated['external_device_id'] ?? null) ?: null,
            'default_pin' => ($validated['default_pin'] ?? null) ?: null,
        ]);

        $existingIds = collect($validated['deviceFunctions'])
            ->pluck('id')
            ->filter()
            ->values()
            ->all();

        $device->deviceFunctions()
            ->whereNotIn('id', $existingIds)
            ->delete();

        foreach ($validated['deviceFunctions'] as $function) {
            if (! empty($function['id'])) {
                DeviceFunction::query()
                    ->where('id', $function['id'])
                    ->where('device_id', $device->id)
                    ->update([
                        'type' => $function['type'],
                        'pin' => $function['pin'],
                    ]);

                continue;
            }

            DeviceFunction::create([
                'device_id' => $device->id,
                'type' => $function['type'],
                'pin' => $function['pin'],
            ]);
        }

        $device->places()->sync($placeIds);
        $syncService->sync($device, $placeIds);

        return redirect()
            ->route('app.devices.show', ['device' => $device->id])
            ->with('status', __('app.device_updated'));
    }
}
