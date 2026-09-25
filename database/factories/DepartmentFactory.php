<?php

namespace Database\Factories;

use App\Domain\MasterData\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Create inside CurrentEntity::run() — entity_id comes from the current entity.
 *
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('DEP-###')),
            'name' => fake()->randomElement(['Production', 'Engineering', 'Finance', 'Procurement', 'Logistics']).' '.fake()->unique()->numberBetween(1, 9999),
            'is_active' => true,
        ];
    }
}
