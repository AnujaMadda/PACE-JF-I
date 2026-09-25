<?php

namespace App\Domain\MasterData\Import;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Base for master data identified by `code` within the entity: a row with an
 * existing code updates that record, a new code creates one. The entity comes
 * from the current entity, never from the file.
 */
abstract class CodeKeyedDefinition extends ImportDefinition
{
    /**
     * @return class-string<Model>
     */
    abstract protected function model(): string;

    /**
     * Attributes to fill (besides code and is_active) from a validated row.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    abstract protected function attributes(array $row, ImportContext $context): array;

    /**
     * Export values for one record, keyed by column (besides the common ones).
     *
     * @return array<string, mixed>
     */
    abstract protected function exportValues(Model $record): array;

    /**
     * @return list<string>
     */
    protected function exportWith(): array
    {
        return [];
    }

    /**
     * @return list<Column>
     */
    protected function codeAndName(string $codeExample, string $nameExample): array
    {
        return [
            Column::make('code')->rules(['required', 'string', 'max:64'])->example($codeExample),
            Column::make('name')->rules(['required', 'string', 'max:255'])->example($nameExample),
        ];
    }

    protected function description(): Column
    {
        return Column::make('description')->rules(['nullable', 'string', 'max:2000']);
    }

    protected function active(): Column
    {
        return Column::make('is_active', 'bool')->rules(['nullable', 'boolean'])->example('yes', 'yes / no; blank = yes for new records');
    }

    public function persist(array $row, ImportContext $context): string
    {
        $class = $this->model();
        $record = $class::query()->where('code', $row['code'])->first();
        $created = $record === null;
        $record ??= new $class(['code' => $row['code']]);

        $record->fill($this->attributes($row, $context));
        $record->fill(['name' => $row['name'], 'description' => $row['description'] ?? $record->getAttribute('description')]);

        if ($row['is_active'] !== null || $created) {
            $record->fill(['is_active' => $row['is_active'] ?? true]);
        }

        $record->save();

        return $created ? 'created' : 'updated';
    }

    public function exportRows(User $user): iterable
    {
        $class = $this->model();

        foreach ($class::query()->with($this->exportWith())->orderBy('code')->lazy(500) as $record) {
            yield array_merge([
                'code' => $record->getAttribute('code'),
                'name' => $record->getAttribute('name'),
                'description' => $record->getAttribute('description'),
                'is_active' => (bool) $record->getAttribute('is_active'),
            ], $this->exportValues($record));
        }
    }
}
