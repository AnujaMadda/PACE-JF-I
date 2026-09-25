<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Core\Models\Entity;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

class SwitchEntity
{
    /**
     * @throws AuthorizationException when the user has no access to the entity
     */
    public function handle(Request $request, User $user, int $entityId): Entity
    {
        $entity = Entity::query()->active()->find($entityId);

        if ($entity === null || ! $user->canAccessEntity($entity)) {
            throw new AuthorizationException(__('You do not have access to that entity.'));
        }

        $from = $request->session()->get('entity_id');

        $request->session()->regenerate();
        $request->session()->put('entity_id', $entity->getKey());

        activity('auth')
            ->causedBy($user)
            ->performedOn($entity)
            ->event('entity_switched')
            ->withProperties(['from_entity_id' => $from, 'to_entity_id' => $entity->getKey(), 'entity_id' => $entity->getKey()])
            ->log("Switched to {$entity->code}");

        return $entity;
    }
}
