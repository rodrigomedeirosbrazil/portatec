<?php

namespace App\Filament\App\Resources;

use App\Enums\DeviceTypeEnum;
use App\Filament\App\Resources\DeviceResource\Pages;
use App\Models\Device;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
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

                Repeater::make('deviceUsers')
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
                    ]),

                Section::make(__('app.device_functions'))
                    ->description(__('app.device_functions_description'))
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
                                    ->required()
                                    ->numeric(),
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->addActionLabel(__('app.add_device_function'))
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
                    $query->whereHas('deviceUsers', function (Builder $query) use ($user) {
                        $query->where('user_id', $user->id);
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
