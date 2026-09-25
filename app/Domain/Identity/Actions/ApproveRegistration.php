<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Core\Models\Entity;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use InvalidArgumentException;

/**
 * Approves a self-registered account: grants entity access and roles, then
 * sends the activation link so the user sets a password.
 */
class ApproveRegistration
{
    public function __construct(
        private readonly SyncEntityRoles $syncEntityRoles,
        private readonly IssueActivationLink $issueActivationLink,
    ) {}

    /**
     * @param  list<int|string>  $roleIds
     */
    public function handle(User $user, Entity $entity, array $roleIds, User $by): void
    {
        if ($user->status !== UserStatus::PendingApproval) {
            throw new InvalidArgumentException('Only pending registrations can be approved.');
        }

        $user->forceFill(['status' => UserStatus::Invited])->save();
        $user->entities()->syncWithoutDetaching([$entity->getKey() => ['is_home' => true, 'granted_by' => $by->getKey()]]);
        $this->syncEntityRoles->handle($user, $entity, $roleIds, $by);

        activity('users')->performedOn($user)->causedBy($by)->event('registration_approved')
            ->withProperties(['entity_id' => $entity->getKey()])->log('Self-registration approved');

        $this->issueActivationLink->handle($user, $by);
    }
}
