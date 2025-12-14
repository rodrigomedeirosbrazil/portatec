<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Booking;

use App\Filament\App\Resources\AccessCodes\AccessCodeResource;
use App\Filament\App\Resources\Booking\Pages\CreateBooking;
use App\Filament\App\Resources\Booking\Pages\EditBooking;
use App\Filament\App\Resources\Booking\Pages\ListBookings;
use App\Filament\App\Resources\Integration\IntegrationResource;
use App\Filament\App\Resources\PlaceResource;
use App\Models\Booking;
use App\Models\Integration;
use App\Models\Place;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $modelLabel = 'Reserva';

    protected static ?string $pluralModelLabel = 'Reservas';

    protected static ?string $recordTitleAttribute = 'guest_name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('place_id')
                    ->label(__('app.place'))
                    ->relationship('place', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('integration_id')
                    ->label(__('app.integration'))
                    ->relationship('integration', 'id', fn (Builder $query) => $query->with('platform'))
                    ->nullable()
                    ->searchable()
                    ->preload(),
                TextInput::make('guest_name')
                    ->label(__('app.guest_name'))
                    ->maxLength(255),
                DateTimePicker::make('check_in')
                    ->label(__('app.check_in'))
                    ->required(),
                DateTimePicker::make('check_out')
                    ->label(__('app.check_out'))
                    ->required(),
                TextInput::make('external_id')
                    ->label(__('app.external_id'))
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('place.name')
                    ->label(__('app.place'))
                    ->searchable()
                    ->sortable()
                    ->url(fn (Booking $record) => PlaceResource::getUrl('edit', ['record' => $record->place])),
                TextColumn::make('integration.id')
                    ->label(__('app.integration'))
                    ->formatStateUsing(fn ($state, Booking $record) => $record->integration
                        ? "ID: {$state}"
                        : '—'
                    )
                    ->url(fn (Booking $record) => $record->integration
                        ? IntegrationResource::getUrl('edit', ['record' => $record->integration])
                        : null
                    ),
                TextColumn::make('guest_name')
                    ->label(__('app.guest_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('check_in')
                    ->label(__('app.check_in'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('check_out')
                    ->label(__('app.check_out'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('accessCode.pin')
                    ->label(__('app.access_code'))
                    ->formatStateUsing(fn ($state, Booking $record) => $state ?? '—')
                    ->url(fn (Booking $record) => $record->accessCode
                        ? AccessCodeResource::getUrl('edit', ['record' => $record->accessCode])
                        : null
                    ),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
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
            'index' => ListBookings::route('/'),
            'create' => CreateBooking::route('/create'),
            'edit' => EditBooking::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
