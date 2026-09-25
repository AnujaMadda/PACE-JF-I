<?php

namespace App\Domain\MasterData\Import;

use App\Domain\Identity\Models\User;

/**
 * Describes one importable/exportable sheet: its columns, per-row validation,
 * how a valid row is saved, and how records are exported (in the same
 * column layout, so an export can be edited and re-imported).
 */
abstract class ImportDefinition
{
    abstract public function key(): string;

    abstract public function label(): string;

    /**
     * @return list<Column>
     */
    abstract protected function columns(): array;

    /**
     * Saves one validated, normalised row. Returns 'created' or 'updated'.
     *
     * @param  array<string, mixed>  $row
     * @return 'created'|'updated'
     */
    abstract public function persist(array $row, ImportContext $context): string;

    /**
     * @return iterable<array<string, mixed>> rows keyed by column key
     */
    abstract public function exportRows(User $user): iterable;

    /**
     * The value identifying a row within the file, for duplicate detection.
     *
     * @param  array<string, mixed>  $row
     */
    public function rowKey(array $row): ?string
    {
        return isset($row['code']) ? strtolower((string) $row['code']) : null;
    }

    public function importPermission(): string
    {
        return 'masterdata.import';
    }

    public function exportPermission(): string
    {
        return 'masterdata.export';
    }

    /**
     * Columns this user may import and export (some are permission-gated).
     *
     * @return list<Column>
     */
    public function columnsFor(User $user): array
    {
        return $this->columns();
    }

    /**
     * @return list<string>
     */
    public function requiredHeadings(User $user): array
    {
        return array_values(array_map(
            fn (Column $c) => $c->key,
            array_filter($this->columnsFor($user), fn (Column $c) => in_array('required', $c->rules, true)),
        ));
    }

    /**
     * Extra row rules on top of each column's own rules.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, list<mixed>>
     */
    public function extraRules(array $row, ImportContext $context): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, list<mixed>>
     */
    public function rules(array $row, ImportContext $context): array
    {
        $rules = [];

        foreach ($this->columnsFor($context->user) as $column) {
            $rules[$column->key] = $column->rules;
        }

        return array_merge_recursive($rules, $this->extraRules($row, $context));
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public function normalise(array $raw, User $user): array
    {
        $row = [];

        foreach ($this->columnsFor($user) as $column) {
            $row[$column->key] = $column->normalise($raw[$column->key] ?? null);
        }

        return $row;
    }

    /**
     * @return list<string>
     */
    public function headings(User $user): array
    {
        return array_map(fn (Column $c) => $c->key, $this->columnsFor($user));
    }

    /**
     * @return list<string>
     */
    public function exampleRow(User $user): array
    {
        return array_map(fn (Column $c) => $c->example, $this->columnsFor($user));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public function formatRow(array $row, User $user): array
    {
        return array_map(fn (Column $c) => $c->format($row[$c->key] ?? null), $this->columnsFor($user));
    }
}
