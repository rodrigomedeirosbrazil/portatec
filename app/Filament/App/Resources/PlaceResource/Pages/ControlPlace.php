<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\PlaceResource\Pages;

use App\Enums\DeviceTypeEnum;
use App\Enums\PlaceRoleEnum;
use App\Events\DevicePulseEvent;
use App\Filament\App\Resources\PlaceResource;
use App\Models\CommandLog;
use App\Models\Place;
use App\Models\PlaceDeviceFunction;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ControlPlace extends Page implements HasTable
{
    use InteractsWithActions;
    use Tables\Concerns\InteractsWithTable;

    protected static string $resource = PlaceResource::class;

    protected string $view = 'filament.app.resources.place-resource.pages.control-place';

    public Place $place;

    public function mount(int|string $record): void
    {
        $this->place = Place::query()->findOrFail($record);

        if (! auth()->user()->hasRole('super_admin') && ! $this->place->hasAccessToPlace(auth()->user())) {
            abort(403);
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('app.control_devices'))
            ->query(fn (): Builder => $this->getPlaceDeviceFunctionsQuery())
            ->columns([
                TextColumn::make('deviceFunction.device.name')
                    ->label(__('app.device'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('deviceFunction.type')
                    ->label(__('app.device_type'))
                    ->formatStateUsing(
                        fn (?DeviceTypeEnum $state): ?string => $state?->label()
                    ),
                TextColumn::make('deviceFunction.pin')
                    ->label('Pin'),
                TextColumn::make('deviceFunction.device_status')
                    ->label(__('app.status'))
                    ->state(fn (PlaceDeviceFunction $record): ?bool => $record->deviceFunction?->status)
                    ->formatStateUsing(function (?bool $state, PlaceDeviceFunction $record): string {
                        if ($record->deviceFunction?->type !== DeviceTypeEnum::Sensor) {
                            return '-';
                        }

                        return $state
                            ? __('app.device_statuses.open')
                            : __('app.device_statuses.closed');
                    }),
            ])
            ->actions([
                Action::make('push')
                    ->label(__('app.push'))
                    ->icon('heroicon-o-bolt')
                    ->visible(
                        fn (PlaceDeviceFunction $record): bool =>
                            $record->deviceFunction?->type === DeviceTypeEnum::Button
                    )
                    ->disabled(
                        fn (PlaceDeviceFunction $record): bool =>
                            ! $record->deviceFunction?->device?->isAvailable()
                    )
                    ->requiresConfirmation()
                    ->action(fn (PlaceDeviceFunction $record) => $this->pushButton($record)),
            ])
            ->paginated(false)
            ->poll('5s');
    }

    protected function getPlaceDeviceFunctionsQuery(): Builder
    {
        return PlaceDeviceFunction::query()
            ->where('place_id', $this->place->id)
            ->with(['deviceFunction.device']);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function pushButton(PlaceDeviceFunction $placeDeviceFunction): void
    {
        $deviceFunction = $placeDeviceFunction->deviceFunction;

        if (! $deviceFunction) {
            Notification::make()
                ->title(__('app.device_not_found'))
                ->danger()
                ->send();

            return;
        }

        try {
            broadcast(new DevicePulseEvent(
                $deviceFunction->device->external_device_id,
                ['pin' => $deviceFunction->pin],
            ));

            CommandLog::create([
                'user_id' => auth()->id(),
                'place_id' => $this->place->id,
                'device_function_id' => $deviceFunction->id,
                'command_type' => 'push_button',
                'device_function_type' => $deviceFunction->type->value ?? null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            Notification::make()
                ->title(__('app.command_sent'))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('app.error_sending_command', ['message' => $e->getMessage()]))
                ->danger()
                ->send();
        }
    }
}
