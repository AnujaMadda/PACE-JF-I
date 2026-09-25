<?php

namespace App\Domain\MasterData\Support;

use App\Domain\Core\Models\Entity;
use App\Domain\Core\Money\Contracts\ExchangeRateProvider;
use App\Domain\Core\Scopes\EntityScope;
use App\Domain\MasterData\Models\ExchangeRate;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;

/**
 * Latest active rate effective on or before the date. Uses the inverse of the
 * opposite pair when only that one is maintained (e.g. KES→USD from USD→KES).
 */
class TableExchangeRateProvider implements ExchangeRateProvider
{
    public function rate(Entity $entity, string $from, string $to, CarbonInterface $on): ?BigDecimal
    {
        if ($from === $to) {
            return BigDecimal::one()->toScale(6);
        }

        if ($direct = $this->find($entity, $from, $to, $on)) {
            return $direct->rate;
        }

        if ($inverse = $this->find($entity, $to, $from, $on)) {
            return BigDecimal::one()->dividedBy($inverse->rate, 6, RoundingMode::HalfUp);
        }

        return null;
    }

    private function find(Entity $entity, string $from, string $to, CarbonInterface $on): ?ExchangeRate
    {
        // Explicit entity filter so the lookup is correct inside jobs and reports too.
        return ExchangeRate::query()
            ->withoutGlobalScope(EntityScope::class)
            ->where('entity_id', $entity->getKey())
            ->where('from_currency', $from)
            ->where('to_currency', $to)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $on->toDateString())
            ->orderByDesc('effective_from')
            ->first();
    }
}
