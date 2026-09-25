<?php

namespace App\Filament\Admin\Resources\Entities\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EntitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label(__('Code'))->searchable()->sortable(),
                TextColumn::make('name')->label(__('Name'))->searchable()->sortable(),
                TextColumn::make('country')->label(__('Country'))->searchable(),
                TextColumn::make('base_currency')->label(__('Currency')),
                TextColumn::make('timezone')->label(__('Timezone')),
                TextColumn::make('users_count')->label(__('Users'))->counts('users'),
                IconColumn::make('is_active')->label(__('Active'))->boolean(),
            ])
            ->defaultSort('code')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
