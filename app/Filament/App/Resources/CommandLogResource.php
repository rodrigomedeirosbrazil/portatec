<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\CommandLogResource\Pages;
use App\Models\CommandLog;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CommandLogResource extends Resource
{
    protected static ?string $model = CommandLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Monitoring';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Log de comandos';

    protected static ?string $pluralModelLabel = 'Logs de comandos';

    protected static function getFieldLabel(string $field): string
    {
        return __('app.command_log_fields.' . $field);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('id')
                            ->label(static::getFieldLabel('id'))
                            ->disabled(),

                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->label(static::getFieldLabel('user'))
                            ->disabled(),

                        Forms\Components\Select::make('place_id')
                            ->relationship('place', 'name')
                            ->label(static::getFieldLabel('place'))
                            ->disabled(),

                        Forms\Components\Select::make('device_id')
                            ->relationship('device', 'name')
                            ->label(static::getFieldLabel('device'))
                            ->disabled(),

                        Forms\Components\TextInput::make('command_type')
                            ->label(static::getFieldLabel('command_type'))
                            ->disabled(),

                        Forms\Components\Textarea::make('command_payload')
                            ->label(static::getFieldLabel('command_payload'))
                            ->disabled()
                            ->columnSpanFull(),

                        

                        Forms\Components\TextInput::make('ip_address')
                            ->label(static::getFieldLabel('ip_address'))
                            ->disabled(),

                        Forms\Components\TextInput::make('user_agent')
                            ->label(static::getFieldLabel('user_agent'))
                            ->disabled()
                            ->columnSpanFull(),

                        Forms\Components\DateTimePicker::make('created_at')
                            ->label(static::getFieldLabel('created_at'))
                            ->disabled(),

                        Forms\Components\DateTimePicker::make('updated_at')
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
                $query->when(! auth()?->user()->hasRole('super_admin'), fn (Builder $query) =>
                    $query->whereHas('place', fn (Builder $query) =>
                        $query->whereHas('placeUsers', fn (Builder $query) =>
                            $query->where('user_id', auth()?->user()->id)
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

                TextColumn::make('device.name')
                    ->label(static::getFieldLabel('device'))
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                

                TextColumn::make('command_payload')
                    ->label(static::getFieldLabel('command_payload'))
                    ->sortable()
                    ->searchable(),

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
                SelectFilter::make('device')
                    ->relationship('device', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('created_at')
                    ->form([
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
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommandLogs::route('/'),
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
