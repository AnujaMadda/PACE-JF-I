<?php

namespace App\Filament\Admin\Resources\ImportRuns;

use App\Domain\MasterData\Import\ImportRegistry;
use App\Domain\MasterData\Import\ImportRun;
use App\Domain\MasterData\Import\ImportStatus;
use App\Filament\Admin\Resources\ImportRuns\Pages\ListImportRuns;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Read-only history of Excel imports in the current entity.
 */
class ImportRunResource extends Resource
{
    protected static ?string $model = ImportRun::class;

    protected static ?string $slug = 'import-history';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Master data';

    protected static ?int $navigationSort = 90;

    protected static ?string $navigationLabel = 'Import history';

    protected static ?string $pluralModelLabel = 'import history';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $types = app(ImportRegistry::class)->options();

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('creator'))
            ->poll('5s')
            ->columns([
                TextColumn::make('created_at')->label(__('Uploaded'))->dateTime()->sortable(),
                TextColumn::make('type')->label(__('Type'))->formatStateUsing(fn (string $state) => $types[$state] ?? $state),
                TextColumn::make('original_name')->label(__('File'))->limit(40),
                TextColumn::make('status')->label(__('Status'))->badge()
                    ->formatStateUsing(fn (ImportStatus $state) => $state->label())
                    ->color(fn (ImportStatus $state) => $state->color()),
                TextColumn::make('total_rows')->label(__('Rows')),
                TextColumn::make('created_rows')->label(__('Created')),
                TextColumn::make('updated_rows')->label(__('Updated')),
                TextColumn::make('failed_rows')->label(__('With errors')),
                TextColumn::make('message')->label(__('Result'))->wrap()->limit(120),
                TextColumn::make('creator.name')->label(__('By')),
            ])
            ->filters([
                SelectFilter::make('type')->options($types),
                SelectFilter::make('status')->options(collect(ImportStatus::cases())->mapWithKeys(fn (ImportStatus $s) => [$s->value => $s->label()])->all()),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('errors')
                    ->label(__('Error report'))
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->color('danger')
                    ->visible(fn (ImportRun $record) => $record->error_report_path !== null)
                    ->url(fn (ImportRun $record) => route('imports.errors', $record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImportRuns::route('/'),
        ];
    }
}
