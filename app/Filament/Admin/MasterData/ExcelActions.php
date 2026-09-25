<?php

namespace App\Filament\Admin\MasterData;

use App\Domain\Identity\Models\User;
use App\Domain\MasterData\Import\ExportSheet;
use App\Domain\MasterData\Import\ImportRegistry;
use App\Domain\MasterData\Import\ImportStatus;
use App\Domain\MasterData\Import\StartImport;
use App\Filament\Admin\Resources\ImportRuns\ImportRunResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Import / Export / Template buttons for any registered import type.
 */
final class ExcelActions
{
    /**
     * @return list<ActionGroup>
     */
    public static function for(string $type): array
    {
        $definition = app(ImportRegistry::class)->get($type);
        $user = fn (): ?User => auth()->user();

        return [
            ActionGroup::make([
                Action::make('import')
                    ->label(__('Import from Excel'))
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->visible(fn () => (bool) $user()?->can($definition->importPermission()))
                    ->modalDescription(__('Rows with an existing code are updated; new codes are created. If any row has an error, nothing is imported and you get an error report.'))
                    ->schema([
                        FileUpload::make('file')
                            ->label(__('Excel file (.xlsx)'))
                            ->storeFiles(false)
                            ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                            ->maxSize(10240)
                            ->required(),
                        Toggle::make('send_activation_links')
                            ->label(__('Email activation links to new users now'))
                            ->default(false)
                            ->visible($type === 'users'),
                    ])
                    ->action(function (array $data) use ($type, $user): void {
                        /** @var TemporaryUploadedFile $file */
                        $file = $data['file'];
                        $run = app(StartImport::class)->handle($type, $file, $user(), array_filter(['send_activation_links' => $data['send_activation_links'] ?? null], fn ($v) => $v !== null));

                        $notification = Notification::make()
                            ->title($run->status === ImportStatus::Completed ? __('Import complete') : ($run->status === ImportStatus::Failed ? __('Import failed') : __('Import queued')))
                            ->body($run->message ?? __('Processing in the background. Check Import history for the result.'))
                            ->actions([Action::make('history')->label(__('Import history'))->url(ImportRunResource::getUrl())]);

                        match ($run->status) {
                            ImportStatus::Completed => $notification->success(),
                            ImportStatus::Failed => $notification->danger(),
                            default => $notification->info(),
                        };

                        $notification->persistent()->send();
                    }),
                Action::make('export')
                    ->label(__('Export to Excel'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn () => (bool) $user()?->can($definition->exportPermission()))
                    ->action(fn () => app(ExportSheet::class)->handle($type, $user())),
                Action::make('template')
                    ->label(__('Download import template'))
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->visible(fn () => (bool) $user()?->can($definition->importPermission()))
                    ->action(fn () => app(ExportSheet::class)->handle($type, $user(), templateOnly: true)),
            ])
                ->label(__('Excel'))
                ->icon(Heroicon::OutlinedTableCells)
                ->button()
                ->color('gray'),
        ];
    }
}
