<?php

namespace App\Filament\App\Resources;

use App\Enums\DeviceTypeEnum;
use App\Enums\DeviceTypeEnum;
use App\Filament\App\Resources\DeviceResource\Pages;
use App\Models\Device;
use App\Models\User;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $modelLabel = 'Dispositivo';

    protected static ?string $pluralModelLabel = 'Dispositivos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('app.device_information'))
                    ->description(__('app.device_information_description'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('app.name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                
                            ]),

                        TextInput::make('chip_id')
                            ->label(__('app.chip_id'))
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Section::make(__('app.device_status'))
                    ->description(__('app.device_status_description'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Placeholder::make('status_display')
                                    ->label(__('app.current_status'))
                                    ->content(function (?Device $record): HtmlString|string {
                                        if (!$record) {
                                            return __('app.new_device');
                                        }

                                        $isAvailable = $record->isAvailable();
                                        $status = $isAvailable ? __('app.online') : __('app.offline');
                                        $color = $isAvailable ? 'success' : 'danger';

                                        return new HtmlString("<span class='fi-badge fi-color-{$color} inline-flex items-center justify-center gap-x-1 rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset'>{$status}</span>");
                                    })
                                    ->columnSpan(1),

                                Placeholder::make('last_sync_display')
                                    ->label(__('app.last_sync'))
                                    ->content(function (?Device $record): string {
                                        if (!$record || !$record->last_sync) {
                                            return __('app.never_synced');
                                        }

                                        return $record->last_sync->diffForHumans();
                                    })
                                    ->columnSpan(1),
                            ]),
                    ])
                    ->visible(fn (?Device $record): bool => $record !== null),

                Section::make(__('app.places'))
                    ->description(__('app.device_places_description'))
                    ->schema([
                        Repeater::make('placeDevices')
                            ->relationship()
                            ->schema([
                                Select::make('place_id')
                                    ->relationship(
                                        'place',
                                        'name',
                                        fn ($query) => $query->whereHas('placeUsers', fn ($query) =>
                                            $query->where('user_id', filament()->auth()->user()->id)
                                                ->where('role', 'admin')
                                        )
                                    )
                                    ->label(__('app.place'))
                                    ->searchable()
                                    ->preload(false)
                                    ->required(),

                                TextInput::make('gpio')
                                    ->label(__('app.gpio'))
                                    ->numeric()
                                    ->required(),

                                Select::make('type')
                                    ->label(__('app.type'))
                                    ->options(DeviceTypeEnum::toArray())
                                    ->searchable()
                                    ->required(),
                            ])
                            ->minItems(1)
                            ->defaultItems(1)
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $user = filament()->auth()->user();

                $query->when(! $user->hasRole('super_admin'), function (Builder $query) use ($user) {
                    $query->whereHas('placeDevices', function (Builder $query) use ($user) {
                        $query->whereHas('place', function (Builder $query) use ($user) {
                            $query->whereHas('placeUsers', function (Builder $query) use ($user) {
                                $query->where('user_id', $user->id);
                            });
                        });
                    });
                });
            })
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('app.name'))
                    ->searchable()
                    ->sortable(),

                

                TextColumn::make('chip_id')
                    ->label(__('app.chip_id'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('app.status'))
                    ->badge()
                    ->getStateUsing(fn (Device $record): string => $record->isAvailable() ? 'online' : 'offline')
                    ->colors([
                        'success' => 'online',
                        'danger' => 'offline',
                    ])
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'online' => __('app.online'),
                        'offline' => __('app.offline'),
                        default => $state,
                    })
                    ->tooltip(fn (Device $record): string =>
                        $record->last_sync
                            ? __('app.last_sync') . ': ' . $record->last_sync->format('d/m/Y H:i:s')
                            : __('app.never_synced')
                    ),

                TextColumn::make('created_at')
                    ->label(__('app.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('app.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDevices::route('/'),
            'create' => Pages\CreateDevice::route('/create'),
            'edit' => Pages\EditDevice::route('/{record}/edit'),
        ];
    }

    protected function paginateTableQuery(Builder $query): CursorPaginator
    {
        return $query->cursorPaginate(
            ($this->getTableRecordsPerPage() === 'all')
                ? $query->count()
                : $this->getTableRecordsPerPage()
        );
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
