<?php

namespace App\Filament\App\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Actions\ViewAction;
use App\Filament\App\Resources\CommandLogResource\Pages\ListCommandLogs;
use App\Filament\App\Resources\CommandLogResource\Pages;
use App\Models\CommandLog;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CommandLogResource extends Resource
{
    protected static ?string $model = CommandLog::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string | \UnitEnum | null $navigationGroup = 'Monitoring';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Log de comandos';

    protected static ?string $pluralModelLabel = 'Logs de comandos';

    protected static function getFieldLabel(string $field): string
    {
        return __('app.command_log_fields.'.$field);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('id')
                            ->label(static::getFieldLabel('id'))
                            ->disabled(),

                        Select::make('user_id')
                            ->relationship('user', 'name')
                            ->label(static::getFieldLabel('user'))
                            ->disabled(),

                        Select::make('place_id')
                            ->relationship('place', 'name')
                            ->label(static::getFieldLabel('place'))
                            ->disabled(),

                        Select::make('device_function_id')
                            ->relationship('deviceFunction')
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->device->name} - {$record->type->value} {$record->pin}")
                            ->label(static::getFieldLabel('device_function'))
                            ->disabled(),

                        TextInput::make('device_function_type')
                            ->label(static::getFieldLabel('device_function_type'))
                            ->disabled(),

                        TextInput::make('command_type')
                            ->label(static::getFieldLabel('command_type'))
                            ->disabled(),

                        Textarea::make('command_payload')
                            ->label(static::getFieldLabel('command_payload'))
                            ->disabled()
                            ->columnSpanFull(),

                        TextInput::make('ip_address')
                            ->label(static::getFieldLabel('ip_address'))
                            ->disabled(),

                        TextInput::make('user_agent')
                            ->label(static::getFieldLabel('user_agent'))
                            ->disabled()
                            ->columnSpanFull(),

                        DateTimePicker::make('created_at')
                            ->label(static::getFieldLabel('created_at'))
                            ->disabled(),

                        DateTimePicker::make('updated_at')
                            ->label(static::getFieldLabel('updated_at'))
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $user = auth()->user();
                $query->when(
                    $user && ! $user->hasRole('super_admin'),
                    fn (Builder $query) => $query->whereHas(
                        'place',
                        fn (Builder $query) => $query->whereHas(
                            'placeUsers',
                            fn (Builder $query) => $query->where('user_id', $user->id)
                        )
                    )
                );
            })
            ->columns([
                TextColumn::make('id')
                    ->label(static::getFieldLabel('id'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('user.name')
                    ->label(static::getFieldLabel('user'))
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('place.name')
                    ->label(static::getFieldLabel('place'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('deviceFunction.device.name')
                    ->label(static::getFieldLabel('device_function'))
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('device_function_type')
                    ->label(static::getFieldLabel('device_function_type'))
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(static::getFieldLabel('created_at'))
                    ->dateTime()
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('place')
                    ->relationship('place', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('deviceFunction.device')
                    ->relationship('deviceFunction.device', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('created_from'),
                        DatePicker::make('created_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommandLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
