<?php

namespace App\Domain\Identity\Rules;

use App\Domain\Identity\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

/**
 * Rejects a password matching any of the user's last N passwords
 * (the current password counts as one of them).
 */
class NotRecentlyUsed implements ValidationRule
{
    public function __construct(
        private readonly User $user,
        private readonly int $count,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->count <= 0 || ! is_string($value)) {
            return;
        }

        $hashes = $this->user->passwordHistories()
            ->latest('id')
            ->limit($this->count - 1)
            ->pluck('password');

        if ($this->user->password !== null) {
            $hashes->prepend($this->user->password);
        }

        if ($hashes->contains(fn (string $hash) => Hash::check($value, $hash))) {
            $fail(__('This password was used recently. Choose a different one.'));
        }
    }
}
