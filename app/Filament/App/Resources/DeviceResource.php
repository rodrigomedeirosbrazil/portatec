<?php

namespace App\Filament\App\Resources;

use App\Enums\DeviceTypeEnum;
use App\Filament\App\Resources\DeviceResource\Pages;
use App\Models\Device;
use Filament\Forms\Components\Repeater;
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
                TextInput::make('name')
                    ->label(__('app.name'))
                    ->required()
                    ->maxLength(255),

                Select::make('type')
                    ->label(__('app.type'))
                    ->options(DeviceTypeEnum::toArray())
                    ->searchable()
                    ->required(),

                TextInput::make('topic')
                    ->label(__('app.topic'))
                    ->maxLength(255),

                TextInput::make('command_topic')
                    ->label(__('app.command_topic'))
                    ->maxLength(255),

                TextInput::make('availability_topic')
                    ->label(__('app.availability_topic'))
                    ->maxLength(255),

                TextInput::make('availability_payload_on')
                    ->maxLength(255),

                TextInput::make('payload_on')
                    ->maxLength(255),

                TextInput::make('payload_off')
                    ->maxLength(255),

                TextInput::make('json_attribute')
                    ->maxLength(255),

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
                            ->searchable()
                            ->preload(false)
                            ->required(),
                    ])
                    ->minItems(1)
                    ->defaultItems(1)
                    ->required()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $query->when(! auth()->user()->hasRole('super_admin'), fn (Builder $query) =>
                    $query->whereHas('placeDevices', fn (Builder $query) =>
                        $query->whereHas('place', fn (Builder $query) =>
                            $query->whereHas('placeUsers', fn (Builder $query) =>
                                $query->where('user_id', auth()->user()->id)
                            )
                        )
                    )
                );
            })
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('type')
                    ->searchable(),
                TextColumn::make('topic')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
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
