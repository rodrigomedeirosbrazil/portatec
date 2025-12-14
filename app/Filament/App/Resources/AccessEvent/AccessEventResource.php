<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\AccessEvent;

use App\Filament\App\Resources\AccessEvent\Pages\ListAccessEvents;
use App\Filament\App\Resources\DeviceResource;
use App\Filament\App\Resources\PlaceResource;
use App\Models\AccessCode;
use App\Models\AccessEvent;
use App\Models\Device;
use App\Models\Place;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AccessEventResource extends Resource
{
    protected static ?string $model = AccessEvent::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $modelLabel = 'Evento de Acesso';

    protected static ?string $pluralModelLabel = 'Eventos de Acesso';

    protected static ?string $recordTitleAttribute = 'id';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                // Filtrar apenas eventos de Places que o usuário tem acesso
                if (!auth()->user()->hasRole('super_admin')) {
                    $user = auth()->user();
                    $query->whereHas('device.place.placeUsers', function (Builder $q) use ($user) {
                        $q->where('user_id', $user->id);
                    });
                }
            })
            ->columns([
                TextColumn::make('device.name')
                    ->label(__('app.device'))
                    ->searchable()
                    ->sortable()
                    ->url(fn (AccessEvent $record) => DeviceResource::getUrl('edit', ['record' => $record->device])),
                TextColumn::make('device.place.name')
                    ->label(__('app.place'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('pin')
                    ->label(__('app.pin'))
                    ->searchable(),
                TextColumn::make('result')
                    ->label(__('app.result'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        'expired' => 'warning',
                        'invalid' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('accessCode.id')
                    ->label(__('app.access_code'))
                    ->formatStateUsing(fn ($state, AccessEvent $record) => $record->accessCode ? "ID: {$state}" : '—'),
                TextColumn::make('server_timestamp')
                    ->label(__('app.timestamp'))
                    ->dateTime()
                    ->sortable()
                    ->default(fn (AccessEvent $record) => $record->device_timestamp ?? $record->server_timestamp),
            ])
            ->filters([
                SelectFilter::make('place_id')
                    ->label(__('app.place'))
                    ->relationship('device.place', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('device_id')
                    ->label(__('app.device'))
                    ->relationship('device', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('result')
                    ->label(__('app.result'))
                    ->options([
                        'success' => __('app.success'),
                        'failed' => __('app.failed'),
                        'expired' => __('app.expired'),
                        'invalid' => __('app.invalid'),
                    ]),
            ])
            ->defaultSort('server_timestamp', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccessEvents::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        // Somente leitura - eventos são criados automaticamente
        return false;
    }
}
