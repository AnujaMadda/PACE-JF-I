<?php

namespace Database\Factories;

use App\Domain\Core\Models\Entity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entity>
 */
class EntityFactory extends Factory
{
    protected $model = Entity::class;

    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->lexify('??'));

        return [
            'code' => $code,
            'name' => 'JF&I '.fake()->country(),
            'country' => fake()->country(),
            'base_currency' => 'USD',
            'timezone' => 'UTC',
            'fy_start_month' => 4,
            'request_prefix' => $code,
            'allowed_email_domains' => ['jfi.lk'],
            'is_active' => true,
        ];
    }

    public function kenya(): static
    {
        return $this->state([
            'code' => 'KE',
            'name' => 'JF&I Packaging Kenya',
            'country' => 'Kenya',
            'base_currency' => 'KES',
            'timezone' => 'Africa/Nairobi',
            'fy_start_month' => 4,
            'request_prefix' => 'KE',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
