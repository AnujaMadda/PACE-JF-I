<?php

namespace Database\Factories;

use App\Domain\Core\Models\Entity;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * A policy-compliant password shared by factory users.
     */
    public const PASSWORD = 'Correct-Horse-42!';

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => Str::lower(fake()->unique()->userName()).'@jfi.lk',
            'designation' => fake()->jobTitle(),
            'status' => UserStatus::Active,
            'password' => static::$password ??= Hash::make(self::PASSWORD),
            'password_changed_at' => now(),
            'activated_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function invited(): static
    {
        return $this->state([
            'status' => UserStatus::Invited,
            'password' => null,
            'password_changed_at' => null,
            'activated_at' => null,
            'invitation_sent_at' => now(),
        ]);
    }

    public function deactivated(): static
    {
        return $this->state(['status' => UserStatus::Deactivated, 'deactivated_at' => now()]);
    }

    public function superAdmin(): static
    {
        return $this->state(['is_group_super_admin' => true]);
    }

    /**
     * Grant access to an entity (optionally as the home entity).
     */
    public function inEntity(Entity $entity, bool $home = true): static
    {
        return $this->afterCreating(fn (User $user) => $user->entities()->attach($entity, ['is_home' => $home]));
    }
}
