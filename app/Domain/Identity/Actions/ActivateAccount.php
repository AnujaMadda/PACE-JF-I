<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

class ActivateAccount
{
    public function __construct(private readonly SetPassword $setPassword) {}

    public function handle(User $user, #[\SensitiveParameter] string $password): void
    {
        DB::transaction(function () use ($user, $password): void {
            $this->setPassword->handle($user, $password, 'activation');

            $user->forceFill([
                'status' => UserStatus::Active,
                'activated_at' => now(),
                'invitation_sent_at' => null,
            ])->save();

            activity('users')
                ->performedOn($user)
                ->causedBy($user)
                ->event('activated')
                ->log('Account activated');
        });
    }
}
