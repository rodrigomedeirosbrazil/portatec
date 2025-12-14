<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Integration;

use App\Filament\App\Resources\Integration\Pages\CreateIntegration;
use App\Filament\App\Resources\Integration\Pages\EditIntegration;
use App\Filament\App\Resources\Integration\Pages\ListIntegrations;
use App\Models\Integration;
use App\Models\Platform;
use App\Models\Place;
use App\Services\ICalSyncService;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Notifications\Notification;

class IntegrationResource extends Resource
{
    protected static ?string $model = Integration::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-link';

    protected static ?string $modelLabel = 'Integração';

    protected static ?string $pluralModelLabel = 'Integrações';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('platform_id')
                    ->label(__('app.platform'))
                    ->relationship('platform', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                // TODO: Implementar gerenciamento de Places relacionados com external_id
                // Por enquanto, usar relacionamento simples - pode ser melhorado depois
                Select::make('places')
                    ->label(__('app.places'))
                    ->relationship('places', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                // Filtrar apenas integrations do usuário logado (exceto super_admin)
                if (!auth()->user()->hasRole('super_admin')) {
                    $query->where('user_id', auth()->id());
                }
            })
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('platform.name')
                    ->label(__('app.platform'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('app.user'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('places_count')
                    ->label(__('app.places_count'))
                    ->counts('places')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('app.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('sync')
                    ->label(__('app.sync_bookings'))
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (Integration $record) {
                        try {
                            $syncService = app(ICalSyncService::class);

                            foreach ($record->places as $place) {
                                $syncService->syncPlaceIntegration($place->id, $record->id);
                            }

                            Notification::make()
                                ->title(__('app.sync_success'))
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title(__('app.sync_error'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->color('primary'),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIntegrations::route('/'),
            'create' => CreateIntegration::route('/create'),
            'edit' => EditIntegration::route('/{record}/edit'),
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
