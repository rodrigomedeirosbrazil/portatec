<?php

namespace App\Filament\App\Resources;

use App\Enums\PlaceRoleEnum;
use App\Filament\App\Resources\PlaceResource\Pages;
use App\Models\Place;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

class PlaceResource extends Resource
{
    protected static ?string $model = Place::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Repeater::make('placeUsers')
                    ->relationship()
                    ->columnSpanFull()
                    ->defaultItems(1)
                    ->minItems(1)
                    ->required()
                    ->schema([
                        Select::make('user_id')
                            ->relationship('user', 'name')
                            ->default(fn () => filament()->auth()->user()->id)
                            ->required(),

                        Select::make('role')
                            ->options(PlaceRoleEnum::class)
                            ->default(PlaceRoleEnum::Admin)
                            ->required(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $query->when(! filament()->auth()->user()->hasRole('super_admin'), fn (Builder $query) =>
                    $query->whereHas('placeUsers', fn (Builder $query) =>
                        $query->where('user_id', filament()->auth()->user()->id)
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
            ->actions([
                Action::make('view')
                    ->url(fn ($record): string => route('place', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Place $record): bool =>
                        filament()->auth()->user()->hasRole('super_admin') ||
                        $record->placeUsers()
                            ->where('user_id', filament()->auth()->user()->id)
                            ->where('role', PlaceRoleEnum::Admin)
                            ->exists()
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlaces::route('/'),
            'create' => Pages\CreatePlace::route('/create'),
            'edit' => Pages\EditPlace::route('/{record}/edit'),
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
