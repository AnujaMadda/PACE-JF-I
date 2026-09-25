<?php

namespace App\Domain\MasterData\Import;

use App\Domain\Core\Models\Entity;
use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Models\User;
use App\Domain\MasterData\Import\Excel\SheetExport;
use App\Domain\MasterData\Import\Excel\SheetReader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Validates every row first. If any row fails, nothing is saved and an error
 * report (the uploaded rows plus an "errors" column) is produced. Otherwise
 * all rows are saved in one transaction. Runs in the run's entity, never the
 * session's.
 */
class ProcessImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public readonly int $importRunId,
        public readonly int $entityId,
    ) {}

    public function handle(CurrentEntity $currentEntity): void
    {
        $entity = Entity::query()->findOrFail($this->entityId);

        $currentEntity->run($entity, function (Entity $entity): void {
            $run = ImportRun::query()->findOrFail($this->importRunId);
            $user = User::query()->findOrFail($run->created_by);
            setPermissionsTeamId($entity->getKey());

            $run->forceFill(['status' => ImportStatus::Running, 'started_at' => now()])->save();

            try {
                $this->process($run, $entity, $user);
            } catch (Throwable $e) {
                report($e);
                $this->finish($run, ImportStatus::Failed, __('The file could not be processed: :error', ['error' => Str::limit($e->getMessage(), 300)]));
            }
        });
    }

    private function process(ImportRun $run, Entity $entity, User $user): void
    {
        $definition = $run->definition();

        if (! $user->can($definition->importPermission())) {
            $this->finish($run, ImportStatus::Failed, __('You do not have permission to import :type.', ['type' => $definition->label()]));

            return;
        }

        $context = new ImportContext($entity, $user, $run->options ?? []);
        $sheets = Excel::toArray(new SheetReader, $run->path, $run->disk);
        // Keys stay the sheet's data-row index, so reported row numbers match Excel even with blank rows skipped.
        $rawRows = array_filter($sheets[0] ?? [], fn (array $row) => array_filter($row, fn ($v) => $v !== null && $v !== '') !== []);

        $headings = array_keys((array) reset($sheets[0]));
        $missing = array_values(array_diff($definition->requiredHeadings($user), $headings));

        if ($rawRows === [] || $missing !== []) {
            $this->finish($run, ImportStatus::Failed, $rawRows === []
                ? __('The file has no data rows.')
                : __('Missing columns: :columns. Download the template for the expected layout.', ['columns' => implode(', ', $missing)]));

            return;
        }

        $rows = [];
        $errors = [];
        $seen = [];

        foreach ($rawRows as $index => $raw) {
            $row = $definition->normalise($raw, $user);
            $validator = Validator::make($row, $definition->rules($row, $context));
            $messages = $validator->errors()->all();

            $key = $definition->rowKey($row);
            if ($key !== null && isset($seen[$key])) {
                $messages[] = __('Duplicate of row :row.', ['row' => $seen[$key] + 2]);
            }
            $seen[$key ?? '#'.$index] = $index;

            $rows[$index] = $row;
            if ($messages !== []) {
                $errors[$index] = $messages;
            }
        }

        $run->forceFill(['total_rows' => count($rows), 'failed_rows' => count($errors)])->save();

        if ($errors !== []) {
            $this->writeErrorReport($run, $definition, $user, $rows, $errors);
            $this->finish($run, ImportStatus::Failed, trans_choice(':count row has errors. Nothing was imported; download the error report, fix the rows and upload again.|:count rows have errors. Nothing was imported; download the error report, fix the rows and upload again.', count($errors)));

            return;
        }

        $counts = ['created' => 0, 'updated' => 0];

        DB::transaction(function () use ($rows, $definition, $context, &$counts): void {
            foreach ($rows as $row) {
                $counts[$definition->persist($row, $context)]++;
            }
        });

        $run->forceFill(['created_rows' => $counts['created'], 'updated_rows' => $counts['updated']])->save();
        $this->finish($run, ImportStatus::Completed, __(':created created, :updated updated.', $counts));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, list<string>>  $errors
     */
    private function writeErrorReport(ImportRun $run, ImportDefinition $definition, User $user, array $rows, array $errors): void
    {
        $path = sprintf('entity-%d/imports/errors/%s.xlsx', $run->entity_id, Str::uuid());
        $lines = [];

        foreach ($rows as $index => $row) {
            $lines[] = [(string) ($index + 2), implode(' | ', $errors[$index] ?? []), ...$definition->formatRow($row, $user)];
        }

        Excel::store(new SheetExport(__('Errors'), ['row', 'errors', ...$definition->headings($user)], $lines), $path, $run->disk);

        $run->forceFill(['error_report_path' => $path])->save();
    }

    private function finish(ImportRun $run, ImportStatus $status, string $message): void
    {
        $run->forceFill(['status' => $status, 'message' => $message, 'finished_at' => now()])->save();

        activity('imports')
            ->performedOn($run)
            ->causedBy($run->created_by)
            ->event($status === ImportStatus::Completed ? 'import_completed' : 'import_failed')
            ->withProperties([
                'entity_id' => $run->entity_id,
                'type' => $run->type,
                'file' => $run->original_name,
                'total' => $run->total_rows,
                'created' => $run->created_rows,
                'updated' => $run->updated_rows,
                'failed' => $run->failed_rows,
            ])
            ->log($message);
    }
}
