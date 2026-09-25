<?php

namespace App\Domain\Core\Money;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Exact decimal columns as Brick\Math\BigDecimal. Floats are refused, so
 * money never passes through binary floating point.
 *
 * Usage: 'amount' => DecimalCast::class.':2' (money), ':6' (exchange rates).
 *
 * @implements CastsAttributes<BigDecimal|null, BigDecimal|string|int|null>
 */
class DecimalCast implements CastsAttributes, SerializesCastableAttributes
{
    public function __construct(private readonly int $scale = 2) {}

    public static function money(): string
    {
        return self::class.':2';
    }

    public static function rate(): string
    {
        return self::class.':6';
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?BigDecimal
    {
        if ($value === null || $value === '') {
            return null;
        }

        return BigDecimal::of((string) $value)->toScale($this->scale, RoundingMode::HalfUp);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return self::normalise($value, $this->scale)?->__toString();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value instanceof BigDecimal ? (string) $value : null;
    }

    /**
     * Parse a user or file value into an exact decimal at the given scale.
     * Accepts BigDecimal, int and numeric strings (thousand separators allowed).
     */
    public static function normalise(mixed $value, int $scale): ?BigDecimal
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_float($value)) {
            throw new InvalidArgumentException('Floats are not accepted for decimal values; pass a string.');
        }

        if ($value instanceof BigDecimal || is_int($value)) {
            return BigDecimal::of($value)->toScale($scale, RoundingMode::HalfUp);
        }

        $string = str_replace([',', ' '], '', trim((string) $value));

        if (! preg_match('/^-?\d+(\.\d+)?$/', $string)) {
            throw new InvalidArgumentException("[{$value}] is not a valid decimal number.");
        }

        return BigDecimal::of($string)->toScale($scale, RoundingMode::HalfUp);
    }
}
