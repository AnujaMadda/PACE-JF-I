<?php

namespace Database\Factories;

use App\Domain\MasterData\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('V-#####')),
            'name' => fake()->company(),
            'email' => fake()->companyEmail(),
            'is_active' => true,
            'bank_name' => 'Equity Bank Kenya',
            'bank_account_name' => fake()->company(),
            'bank_account_number' => fake()->numerify('01########'),
        ];
    }
}
