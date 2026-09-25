<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Core\Models\Entity;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Admin pre-creates a user with access to an entity and roles there. The
 * user then activates the account through Sign Up (or the emailed link).
 */
class InviteUser
{
    public function __construct(
        private readonly SyncEntityRoles $syncEntityRoles,
        private readonly IssueActivationLink $issueActivationLink,
    ) {}

    /**
     * @param  array{name: string, email: string, designation?: string|null, department?: string|null, line_manager_id?: int|null}  $data
     * @param  list<int|string>  $roleIds
     */
    public function handle(array $data, Entity $entity, array $roleIds, User $by, bool $isHome = true, bool $sendLink = true): User
    {
        $data['email'] = Str::lower(trim($data['email']));

        if (! $entity->allowsEmail($data['email'])) {
            throw ValidationException::withMessages([
                'email' => __('The email domain is not allowed for :entity.', ['entity' => $entity->name]),
            ]);
        }

        $user = DB::transaction(function () use ($data, $entity, $roleIds, $by, $isHome): User {
            $user = new User($data);
            $user->forceFill(['status' => UserStatus::Invited])->save();

            $user->entities()->attach($entity, ['is_home' => $isHome, 'granted_by' => $by->getKey()]);
            $this->syncEntityRoles->handle($user, $entity, $roleIds, $by);

            activity('users')
                ->performedOn($user)
                ->causedBy($by)
                ->event('invited')
                ->withProperties(['entity_id' => $entity->getKey()])
                ->log('User invited');

            return $user;
        });

        if ($sendLink) {
            $this->issueActivationLink->handle($user, $by);
        }

        return $user;
    }
}
