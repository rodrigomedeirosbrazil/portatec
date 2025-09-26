<?php

namespace App\Filament\App\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\App\Resources\DeviceResource\Pages\ListDevices;
use App\Filament\App\Resources\DeviceResource\Pages\CreateDevice;
use App\Filament\App\Resources\DeviceResource\Pages\EditDevice;
use App\Enums\DeviceTypeEnum;
use App\Filament\App\Resources\DeviceResource\Pages;
use App\Models\Device;
use Carbon\Carbon;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $modelLabel = 'Dispositivo';

    protected static ?string $pluralModelLabel = 'Dispositivos';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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

                        TextInput::make('last_sync')
                            ->label(__('app.last_sync'))
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(function ($state) {
                                if (! $state) {
                                    return 'Nunca sincronizado';
                                }

                                $lastSync = is_string($state) ? Carbon::createFromFormat('d/m/Y H:i:s', $state) : $state;
                                return $lastSync->format('d/m/Y H:i:s');
                            })
                            ->helperText(function ($state) {
                                if (! $state) {
                                    return 'Este dispositivo nunca foi sincronizado';
                                }

                                $lastSync = is_string($state) ? Carbon::createFromFormat('d/m/Y H:i:s', $state) : $state;
                                $diff = $lastSync->diffForHumans();
                                return "Último sync: {$diff}";
                            })
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

                IconColumn::make('status')
                    ->label(__('app.status'))
                    ->boolean()
                    ->getStateUsing(fn (Device $record): bool => $record->isAvailable())
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip(function (Device $record): string {
                        if (! $record->last_sync) {
                            return 'Nunca sincronizado';
                        }

                        $diff = $record->last_sync->diffForHumans();
                        return "Último sync: {$diff}";
                    }),
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
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDevices::route('/'),
            'create' => CreateDevice::route('/create'),
            'edit' => EditDevice::route('/{record}/edit'),
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
