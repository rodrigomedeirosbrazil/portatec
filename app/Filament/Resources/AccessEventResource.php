<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AccessEventResource\Pages\ListAccessEvents;
use App\Models\AccessEvent;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AccessEventResource extends Resource
{
    protected static ?string $model = AccessEvent::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-key';

    protected static string | \UnitEnum | null $navigationGroup = 'Monitoring';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Evento de acesso';

    protected static ?string $pluralModelLabel = 'Eventos de acesso';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('id')->label('ID')->disabled(),
                        Select::make('device_id')->relationship('device', 'name')->label('Dispositivo')->disabled(),
                        Select::make('access_code_id')->relationship('accessCode', 'id')->label('Access Code')->disabled(),
                        TextInput::make('pin')->label('PIN')->disabled(),
                        TextInput::make('result')->label('Resultado')->disabled(),
                        DateTimePicker::make('device_timestamp')->label('Timestamp dispositivo')->disabled(),
                        DateTimePicker::make('server_timestamp')->label('Timestamp servidor')->disabled(),
                        Textarea::make('metadata')
                            ->label('Metadata')
                            ->formatStateUsing(static fn ($state): ?string => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $state)
                            ->disabled()
                            ->columnSpanFull(),
                        DateTimePicker::make('created_at')->label('Criado em')->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->searchable(),
                TextColumn::make('device.name')->label('Dispositivo')->sortable()->searchable(),
                TextColumn::make('pin')->label('PIN')->sortable()->searchable(),
                TextColumn::make('result')->label('Resultado')->badge()->sortable(),
                TextColumn::make('device_timestamp')->label('Timestamp dispositivo')->dateTime()->sortable()->toggleable(),
                TextColumn::make('server_timestamp')->label('Timestamp servidor')->dateTime()->sortable(),
                TextColumn::make('created_at')->label('Criado em')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('device')->relationship('device', 'name')->searchable()->preload(),
                SelectFilter::make('result')
                    ->options([
                        'success' => 'success',
                        'failed' => 'failed',
                        'expired' => 'expired',
                        'invalid' => 'invalid',
                    ]),
                Filter::make('server_timestamp')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('server_timestamp', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('server_timestamp', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('server_timestamp', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccessEvents::route('/'),
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
