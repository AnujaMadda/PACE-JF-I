<?php

namespace App\Domain\MasterData\Import\Excel;

use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Reads an uploaded workbook as rows keyed by snake_case headings
 * ("Head Email" -> head_email). Formulas are evaluated.
 */
class SheetReader implements Import, WithCalculatedFormulas, WithHeadingRow {}
