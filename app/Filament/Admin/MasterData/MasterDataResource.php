<?php

namespace App\Filament\Admin\MasterData;

use App\Domain\Core\Support\CurrentEntity;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/**
 * Base for per-entity master data screens. Queries are limited to the current
 * entity by the model's global scope (BelongsToEntity); records outside it
 * cannot be listed, opened or acted on. Records are deactivated, never deleted.
 */
abstract class MasterDataResource extends Resource
{
    protected static string|UnitEnum|null $navigationGroup = 'Master data';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Key in the ImportRegistry (import, export and template buttons), or null.
     */
    abstract public static function importType(): ?string;

    public static function codeInput(string $placeholder = ''): TextInput
    {
        return TextInput::make('code')
            ->label(__('Code'))
            ->required()
            ->maxLength(64)
            ->placeholder($placeholder)
            ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('entity_id', app(CurrentEntity::class)->id()));
    }

    public static function nameInput(): TextInput
    {
        return TextInput::make('name')->label(__('Name'))->required()->maxLength(255);
    }

    public static function descriptionInput(): Textarea
    {
        return Textarea::make('description')->label(__('Description'))->rows(2)->maxLength(2000)->columnSpanFull();
    }

    public static function activeToggle(): Toggle
    {
        return Toggle::make('is_active')->label(__('Active'))->default(true)
            ->helperText(__('Inactive records stay on existing requests but cannot be chosen for new ones.'));
    }

    /**
     * @return list<TextColumn>
     */
    public static function codeNameColumns(): array
    {
        return [
            TextColumn::make('code')->label(__('Code'))->searchable()->sortable()->weight('medium'),
            TextColumn::make('name')->label(__('Name'))->searchable()->sortable()->wrap(),
        ];
    }

    /**
     * Adds the active column and filter, the edit action and deactivate/reactivate.
     *
     * @param  list<mixed>  $columns
     * @param  list<mixed>  $filters
     */
    public static function standardTable(Table $table, array $columns, array $filters = [], string $sort = 'code'): Table
    {
        return $table
            ->columns([
                ...$columns,
                IconColumn::make('is_active')->label(__('Active'))->boolean()->sortable(),
                TextColumn::make('updated_at')->label(__('Updated'))->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label(__('Active'))->default(true),
                ...$filters,
            ])
            ->defaultSort($sort)
            ->recordActions([
                EditAction::make()->using(fn (Model $record, array $data) => static::updateRecord($record, $data)),
                self::toggleActiveAction(),
            ]);
    }

    /**
     * Save hooks used by the create and edit modals; override for extra work (e.g. attachments).
     *
     * @param  array<string, mixed>  $data
     */
    public static function createRecord(array $data): Model
    {
        $class = static::getModel();

        return $class::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function updateRecord(Model $record, array $data): Model
    {
        $record->update($data);

        return $record;
    }

    public static function toggleActiveAction(): Action
    {
        return Action::make('toggleActive')
            ->label(fn (Model $record) => $record->getAttribute('is_active') ? __('Deactivate') : __('Reactivate'))
            ->icon(fn (Model $record) => $record->getAttribute('is_active') ? Heroicon::OutlinedNoSymbol : Heroicon::OutlinedArrowPath)
            ->color(fn (Model $record) => $record->getAttribute('is_active') ? 'danger' : 'gray')
            ->requiresConfirmation()
            ->modalDescription(fn (Model $record) => $record->getAttribute('is_active')
                ? __('It will no longer be offered for new requests. History is kept and you can reactivate it later.')
                : null)
            ->visible(function (Model $record): bool {
                $user = auth()->user();

                return $user !== null && $user->can('update', $record) && $user->can('masterdata.manage');
            })
            ->action(fn (Model $record) => $record->forceFill(['is_active' => ! $record->getAttribute('is_active')])->save());
    }
}
