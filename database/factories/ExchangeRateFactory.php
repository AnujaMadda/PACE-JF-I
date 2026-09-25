<?php

namespace Database\Factories;

use App\Domain\MasterData\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRate>
 */
class ExchangeRateFactory extends Factory
{
    protected $model = ExchangeRate::class;

    public function definition(): array
    {
        return [
            'from_currency' => 'USD',
            'to_currency' => 'KES',
            'rate' => '129.500000',
            'effective_from' => now()->startOfMonth()->toDateString(),
            'source' => 'Test',
            'is_active' => true,
        ];
    }
}
