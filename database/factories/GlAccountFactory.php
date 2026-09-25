<?php

namespace Database\Factories;

use App\Domain\MasterData\Enums\GlAccountType;
use App\Domain\MasterData\Models\GlAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GlAccount>
 */
class GlAccountFactory extends Factory
{
    protected $model = GlAccount::class;

    public function definition(): array
    {
        return [
            'code' => (string) fake()->unique()->numberBetween(100000, 999999),
            'name' => 'GL '.fake()->words(2, true),
            'type' => GlAccountType::Capex,
            'is_active' => true,
        ];
    }
}
