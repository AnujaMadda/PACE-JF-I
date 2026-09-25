<?php

namespace App\Domain\MasterData\Import;

use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Models\User;
use App\Domain\MasterData\Import\Excel\SheetExport;
use Illuminate\Auth\Access\AuthorizationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Downloads the current entity's records (or an empty template) in the
 * import layout. Exports are audited.
 */
class ExportSheet
{
    public function __construct(
        private readonly ImportRegistry $registry,
        private readonly CurrentEntity $currentEntity,
    ) {}

    public function handle(string $type, User $by, bool $templateOnly = false): BinaryFileResponse
    {
        $definition = $this->registry->get($type);
        $entity = $this->currentEntity->require();
        $permission = $templateOnly ? $definition->importPermission() : $definition->exportPermission();

        if (! $by->can($permission)) {
            throw new AuthorizationException;
        }

        $rows = $templateOnly
            ? [$definition->exampleRow($by)]
            : (function () use ($definition, $by) {
                foreach ($definition->exportRows($by) as $row) {
                    yield $definition->formatRow($row, $by);
                }
            })();

        if (! $templateOnly) {
            activity('exports')
                ->causedBy($by)
                ->event('master_data_exported')
                ->withProperties(['type' => $type, 'entity_id' => $entity->getKey()])
                ->log("{$definition->label()} exported");
        }

        $name = sprintf('pace-%s-%s-%s.xlsx', strtolower($entity->code), str_replace('_', '-', $type), $templateOnly ? 'template' : now()->format('Ymd-His'));

        return Excel::download(new SheetExport($definition->label(), $definition->headings($by), $rows), $name);
    }
}
