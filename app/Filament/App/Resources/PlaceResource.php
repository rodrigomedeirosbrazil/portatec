<?php

namespace App\Filament\App\Resources;

use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\App\Resources\PlaceResource\Pages\ListPlaces;
use App\Filament\App\Resources\PlaceResource\Pages\CreatePlace;
use App\Filament\App\Resources\PlaceResource\Pages\EditPlace;
use App\Enums\PlaceRoleEnum;
use App\Filament\App\Resources\PlaceResource\Pages;
use App\Models\DeviceFunction;
use App\Models\Place;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

class PlaceResource extends Resource
{
    protected static ?string $model = Place::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $modelLabel = 'Local';

    protected static ?string $pluralModelLabel = 'Locais';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('app.name'))
                    ->required()
                    ->maxLength(255),

                Repeater::make('placeUsers')
                    ->label(__('app.users'))
                    ->relationship()
                    ->columnSpanFull()
                    ->defaultItems(1)
                    ->minItems(1)
                    ->required()
                    ->schema([
                        Select::make('user_id')
                            ->relationship('user', 'name')
                            ->label(__('app.user'))
                            ->searchable()
                            ->preload(false)
                            ->required(),

                        Select::make('role')
                            ->label(__('app.role'))
                            ->options(PlaceRoleEnum::class)
                            ->default(PlaceRoleEnum::Admin)
                            ->required(),
                    ]),

                Repeater::make('placeDeviceFunctions')
                    ->minItems(1)
                    ->defaultItems(1)
                    ->required()
                    ->columnSpanFull()
                    ->relationship()
                    ->schema([
                        Select::make('device_function_id')
                            ->relationship(
                                name: 'deviceFunction',
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->device->name} - {$record->type->value} {$record->pin}")
                            ->label(__('app.device_function'))
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) {
                                return DeviceFunction::query()
                                    ->whereHas('device', fn ($query) => $query->where('name', 'like', "%{$search}%")
                                    )
                                    ->whereHas('device', fn (Builder $query) => $query->whereHas('deviceUsers', fn (Builder $query) => $query->where('user_id', filament()->auth()->user()->id)
                                    )
                                    )
                                    ->limit(10)
                                    ->get()
                                    ->mapWithKeys(fn ($record) => [
                                        $record->getKey() => "{$record->device->name} - {$record->type->value} {$record->pin}",
                                    ]);
                            })
                            ->preload(false)
                            ->required(),
                    ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $query->when(! auth()->user()->hasRole('super_admin'), fn (Builder $query) => $query->whereHas('placeUsers', fn (Builder $query) => $query->where('user_id', filament()->auth()->user()->id)
                )
                );
            })
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('view')
                    ->label(__('app.view'))
                    ->url(fn ($record): string => route('place', $record))
                    ->openUrlInNewTab(),
                EditAction::make()
                    ->visible(fn (Place $record): bool => auth()->user()->hasRole('super_admin') ||
                        $record->placeUsers()
                            ->where('user_id', auth()->user()->id)
                            ->where('role', PlaceRoleEnum::Admin)
                            ->exists()
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlaces::route('/'),
            'create' => CreatePlace::route('/create'),
            'edit' => EditPlace::route('/{record}/edit'),
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
}
