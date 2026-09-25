<?php

namespace Database\Factories;

use App\Domain\MasterData\Models\CostCentre;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CostCentre>
 */
class CostCentreFactory extends Factory
{
    protected $model = CostCentre::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('CC-####')),
            'name' => 'Cost centre '.fake()->unique()->numberBetween(1, 99999),
            'is_active' => true,
        ];
    }
}
