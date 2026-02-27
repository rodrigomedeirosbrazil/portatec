<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\DeviceBrandEnum;
use App\Enums\DeviceTypeEnum;
use App\Filament\Resources\DeviceResource\Pages\CreateDevice;
use App\Filament\Resources\DeviceResource\Pages\EditDevice;
use App\Filament\Resources\DeviceResource\Pages\ListDevices;
use App\Models\Device;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $modelLabel = 'Dispositivo';

    protected static ?string $pluralModelLabel = 'Dispositivos';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('app.device_information'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('app.name'))
                                    ->required()
                                    ->maxLength(255),
                                Select::make('place_id')
                                    ->label(__('app.place'))
                                    ->relationship('place', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('external_device_id')
                                    ->label(__('app.external_device_id'))
                                    ->maxLength(255),
                                Select::make('brand')
                                    ->label(__('app.brand'))
                                    ->options(DeviceBrandEnum::class)
                                    ->default(DeviceBrandEnum::Portatec)
                                    ->required(),
                                TextInput::make('default_pin')
                                    ->label(__('app.default_pin'))
                                    ->numeric()
                                    ->minLength(6)
                                    ->maxLength(6)
                                    ->mask('999999'),
                            ]),
                    ]),

                Section::make(__('app.device_functions'))
                    ->schema([
                        Repeater::make('deviceFunctions')
                            ->relationship()
                            ->schema([
                                Select::make('type')
                                    ->label(__('app.type'))
                                    ->options(DeviceTypeEnum::class)
                                    ->required(),
                                TextInput::make('pin')
                                    ->label(__('app.pin'))
                                    ->required(),
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('app.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('place.name')
                    ->label(__('app.place'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('external_device_id')
                    ->label(__('app.external_device_id'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('brand')
                    ->label(__('app.brand'))
                    ->badge()
                    ->sortable(),
                IconColumn::make('status')
                    ->label(__('app.status'))
                    ->boolean()
                    ->getStateUsing(fn (Device $record): bool => $record->isAvailable())
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                TextColumn::make('last_sync')
                    ->label(__('app.last_sync'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDevices::route('/'),
            'create' => CreateDevice::route('/create'),
            'edit' => EditDevice::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([
            SoftDeletingScope::class,
        ]);
    }
}
