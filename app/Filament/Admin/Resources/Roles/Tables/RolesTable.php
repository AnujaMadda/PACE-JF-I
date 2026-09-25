<?php

namespace App\Filament\Admin\Resources\Roles\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('Role'))->searchable()->sortable(),
                TextColumn::make('permissions_count')->label(__('Permissions'))->counts('permissions'),
                TextColumn::make('users_count')->label(__('People'))->counts('users'),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
