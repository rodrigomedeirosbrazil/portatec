<?php

namespace App\Filament\App\Resources\AccessPins;

use App\Filament\App\Resources\AccessPins\Pages\CreateAccessPin;
use App\Filament\App\Resources\AccessPins\Pages\ManageAccessPins;
use App\Models\AccessPin;
use App\Models\Place;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AccessPinResource extends Resource
{
    protected static ?string $model = AccessPin::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-key';

    protected static ?string $modelLabel = 'PIN de Acesso';

    protected static ?string $pluralModelLabel = 'PINs de Acesso';

    protected static ?string $recordTitleAttribute = 'pin';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('place_id')
                    ->label(__('app.place'))
                    ->options(Place::all()->pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                Select::make('user_id')
                    ->label(__('app.user'))
                    ->options(User::all()->pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                TextInput::make('pin')
                    ->label(__('app.pin'))
                    ->required()
                    ->numeric()
                    ->minLength(6)
                    ->maxLength(6)
                    ->mask('999999'),
                DateTimePicker::make('start')
                    ->label(__('app.start'))
                    ->required(),
                DateTimePicker::make('end')
                    ->label(__('app.end'))
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('place.name')
                    ->label(__('app.place'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('app.user'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('pin')
                    ->label(__('app.pin'))
                    ->searchable(),
                TextColumn::make('start')
                    ->label(__('app.start'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('end')
                    ->label(__('app.end'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
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
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAccessPins::route('/'),
            'create' => CreateAccessPin::route('/create'),
        ];
    }
}
