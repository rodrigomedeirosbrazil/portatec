<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\PlaceRoleEnum;
use App\Filament\Resources\PlaceResource\Pages\CreatePlace;
use App\Filament\Resources\PlaceResource\Pages\EditPlace;
use App\Filament\Resources\PlaceResource\Pages\ListPlaces;
use App\Models\Place;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlaceResource extends Resource
{
    protected static ?string $model = Place::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-map-pin';

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
                            ->preload()
                            ->required(),

                        Select::make('role')
                            ->label(__('app.role'))
                            ->options(PlaceRoleEnum::class)
                            ->default(PlaceRoleEnum::Admin)
                            ->required(),

                        TextInput::make('label')
                            ->label(__('app.label'))
                            ->maxLength(255),
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
                TextColumn::make('place_users_count')
                    ->label(__('app.users'))
                    ->counts('placeUsers'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
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
            'index' => ListPlaces::route('/'),
            'create' => CreatePlace::route('/create'),
            'edit' => EditPlace::route('/{record}/edit'),
        ];
    }
}
