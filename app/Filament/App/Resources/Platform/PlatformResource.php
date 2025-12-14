<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\Platform;

use App\Filament\App\Resources\Platform\Pages\ListPlatforms;
use App\Models\Platform;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlatformResource extends Resource
{
    protected static ?string $model = Platform::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $modelLabel = 'Plataforma';

    protected static ?string $pluralModelLabel = 'Plataformas';

    protected static ?string $recordTitleAttribute = 'name';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('app.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('app.slug'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('integrations_count')
                    ->label(__('app.integrations_count'))
                    ->counts('integrations')
                    ->sortable(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlatforms::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        // Somente super_admin pode criar platforms
        return auth()->user()?->hasRole('super_admin') ?? false;
    }
}
