<?php

namespace App\Domain\Core\Money\Contracts;

use App\Domain\Core\Models\Entity;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;

/**
 * Supplies the rate to convert 1 unit of $from into $to for an entity on a date.
 * The default implementation reads the exchange_rates master; a bank or
 * treasury feed can replace it later.
 */
interface ExchangeRateProvider
{
    public function rate(Entity $entity, string $from, string $to, CarbonInterface $on): ?BigDecimal;
}
