<?php

namespace App\Filament\Admin\Resources\Users\RelationManagers;

use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Models\Delegation;
use App\Domain\Identity\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Delegates who act for this user in the current entity for a date range.
 * The workflow engine (Phase 4) uses these; "approved by X on behalf of Y"
 * appears in the timeline.
 */
class DelegationsRelationManager extends RelationManager
{
    protected static string $relationship = 'delegations';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Delegation');
    }

    public function form(Schema $schema): Schema
    {
        $entityId = app(CurrentEntity::class)->id();

        return $schema->components([
            Select::make('delegate_id')
                ->label(__('Delegate'))
                ->options(fn () => User::query()
                    ->whereHas('entities', fn (Builder $e) => $e->whereKey($entityId))
                    ->whereKeyNot($this->getOwnerRecord()->getKey())
                    ->where('status', 'active')
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->required(),
            DateTimePicker::make('starts_at')->label(__('From'))->required()->default(now()),
            DateTimePicker::make('ends_at')->label(__('Until'))->required()->after('starts_at'),
            TextInput::make('reason')->label(__('Reason'))->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        $entityId = app(CurrentEntity::class)->id();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where(fn (Builder $q) => $q->where('entity_id', $entityId)->orWhereNull('entity_id'))->with('delegate'))
            ->columns([
                TextColumn::make('delegate.name')->label(__('Delegate')),
                TextColumn::make('starts_at')->label(__('From'))->dateTime(),
                TextColumn::make('ends_at')->label(__('Until'))->dateTime(),
                TextColumn::make('reason')->label(__('Reason'))->placeholder('—'),
                TextColumn::make('state')->label(__('State'))->badge()
                    ->state(fn (Delegation $record) => match (true) {
                        $record->revoked_at !== null => __('Revoked'),
                        $record->ends_at->isPast() => __('Ended'),
                        $record->starts_at->isFuture() => __('Scheduled'),
                        default => __('Active'),
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('Set delegate'))
                    ->mutateDataUsing(function (array $data) use ($entityId): array {
                        $data['entity_id'] = $entityId;

                        return $data;
                    })
                    ->after(function (Delegation $record): void {
                        $record->forceFill(['created_by' => auth()->id()])->saveQuietly();
                    }),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label(__('Revoke'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Delegation $record) => $record->revoked_at === null && $record->ends_at->isFuture())
                    ->action(fn (Delegation $record) => $record->forceFill(['revoked_at' => now()])->save()),
            ])
            ->defaultSort('starts_at', 'desc');
    }
}
