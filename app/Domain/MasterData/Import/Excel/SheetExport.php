<?php

namespace App\Domain\MasterData\Import\Excel;

use Generator;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * A single-sheet workbook with a bold heading row. Used for exports,
 * templates and import error reports (which add an "errors" column).
 */
class SheetExport implements Export, FromGenerator, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  list<string>  $headings
     * @param  iterable<list<string>>  $rows
     */
    public function __construct(
        private readonly string $title,
        private readonly array $headings,
        private readonly iterable $rows,
    ) {}

    public function generator(): Generator
    {
        foreach ($this->rows as $row) {
            yield $row;
        }
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return mb_substr($this->title, 0, 31);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');

        return [1 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E6EAF8']]]];
    }
}
