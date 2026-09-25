<?php

namespace App\Domain\MasterData\Import;

use App\Domain\Core\Money\DecimalCast;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * One column of an import/export sheet. Values are normalised by type before
 * validation, so numbers typed in Excel never pass through floats into money.
 */
final readonly class Column
{
    /**
     * @param  'string'|'int'|'decimal'|'rate'|'date'|'bool'  $type
     * @param  list<mixed>  $rules  validation rules applied after normalising
     */
    public function __construct(
        public string $key,
        public string $type = 'string',
        public array $rules = [],
        public string $example = '',
        public string $help = '',
    ) {}

    public static function make(string $key, string $type = 'string'): self
    {
        return new self($key, $type);
    }

    /**
     * @param  list<mixed>  $rules
     */
    public function rules(array $rules): self
    {
        return new self($this->key, $this->type, $rules, $this->example, $this->help);
    }

    public function example(string $example, string $help = ''): self
    {
        return new self($this->key, $this->type, $this->rules, $example, $help);
    }

    public function normalise(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === null || $value === '') {
            return null;
        }

        return match ($this->type) {
            'string' => is_float($value) ? self::floatToString($value) : (string) $value,
            'int' => is_numeric($value) ? (string) (int) $value : (string) $value,
            'decimal', 'rate' => self::decimal($value, $this->type === 'rate' ? 6 : 2),
            'date' => self::date($value),
            'bool' => self::bool($value),
        };
    }

    public function format(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'yes' : 'no',
            $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
            $value instanceof \BackedEnum => (string) $value->value,
            default => (string) $value,
        };
    }

    private static function floatToString(float $value): string
    {
        return floor($value) === $value ? number_format($value, 0, '.', '') : rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
    }

    private static function decimal(mixed $value, int $scale): string
    {
        // Excel hands numeric cells over as floats; format them at a fixed precision first.
        $string = is_float($value) ? sprintf('%.'.($scale + 4).'F', $value) : (string) $value;

        try {
            return (string) DecimalCast::normalise($string, $scale);
        } catch (Throwable) {
            return $string; // left for the 'numeric' rule to report
        }
    }

    private static function date(mixed $value): string
    {
        try {
            if (is_int($value) || is_float($value)) {
                return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject($value))->toDateString();
            }

            return CarbonImmutable::parse((string) $value)->toDateString();
        } catch (Throwable) {
            return (string) $value; // left for the 'date' rule to report
        }
    }

    private static function bool(mixed $value): mixed
    {
        $text = strtolower(trim((string) $value));

        return match ($text) {
            '1', 'yes', 'y', 'true', 'active' => true,
            '0', 'no', 'n', 'false', 'inactive' => false,
            default => $value,
        };
    }
}
