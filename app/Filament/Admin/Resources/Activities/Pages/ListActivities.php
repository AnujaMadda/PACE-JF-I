<?php

namespace App\Filament\Admin\Resources\Activities\Pages;

use App\Domain\Audit\Models\Activity;
use App\Domain\Core\Support\LocalTime;
use App\Filament\Admin\Resources\Activities\ActivityResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListActivities extends ListRecords
{
    protected static string $resource = ActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('Export CSV'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn () => (bool) auth()->user()?->can('export', Activity::class))
                ->action(fn () => $this->export()),
        ];
    }

    /**
     * Streams the currently filtered audit entries (entity-scoped) as CSV.
     * Excel exports arrive with the reporting module; the export itself is audited.
     */
    private function export(): StreamedResponse
    {
        abort_unless((bool) auth()->user()?->can('export', Activity::class), 403);

        $query = $this->getFilteredSortedTableQuery();

        activity('audit')
            ->causedBy(auth()->user())
            ->event('audit_exported')
            ->withProperties(['rows' => (clone $query)->count(), 'filters' => $this->tableFilters])
            ->log('Audit log exported');

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['When', 'By', 'Area', 'Event', 'Description', 'Record', 'Entity', 'IP', 'Changes', 'Details'], escape: '\\');

            $query->with(['causer', 'entity'])->reorder()->lazyByIdDesc(500)->each(function (Model $a) use ($out): void {
                if (! $a instanceof Activity) {
                    return;
                }

                fputcsv($out, array_map(self::safeCell(...), [
                    LocalTime::format($a->created_at, 'Y-m-d H:i:s'),
                    $a->causer?->getAttribute('name') ?? 'System',
                    (string) $a->log_name,
                    (string) $a->event,
                    $a->description,
                    $a->subject_type ? class_basename($a->subject_type).' #'.$a->subject_id : '',
                    $a->entity->code ?? 'Group',
                    (string) $a->ip_address,
                    json_encode($a->attribute_changes) ?: '',
                    json_encode($a->properties) ?: '',
                ]), escape: '\\');
            });

            fclose($out);
        }, 'pace-audit-log-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Prevent spreadsheet formula injection when the CSV is opened in Excel.
     */
    private static function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
