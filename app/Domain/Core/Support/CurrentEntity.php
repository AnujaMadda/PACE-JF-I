<?php

namespace App\Domain\Core\Support;

use App\Domain\Core\Exceptions\NoCurrentEntity;
use App\Domain\Core\Models\Entity;

/**
 * The entity the current request, job or command is working in.
 *
 * Web requests set it from the session (EnsureEntitySelected). Queued jobs
 * and console commands must set it explicitly with run(). Setting it also
 * points Spatie's permission team at the entity, so roles resolve per entity.
 */
class CurrentEntity
{
    private ?Entity $entity = null;

    public function set(?Entity $entity): void
    {
        $this->entity = $entity;

        setPermissionsTeamId($entity?->getKey());
    }

    public function get(): ?Entity
    {
        return $this->entity;
    }

    public function require(): Entity
    {
        return $this->entity ?? throw new NoCurrentEntity;
    }

    public function id(): ?int
    {
        return $this->entity?->getKey();
    }

    public function has(): bool
    {
        return $this->entity !== null;
    }

    /**
     * Run a callback inside the given entity, restoring the previous one afterwards.
     *
     * @template T
     *
     * @param  callable(Entity): T  $callback
     * @return T
     */
    public function run(Entity $entity, callable $callback): mixed
    {
        $previous = $this->entity;
        $this->set($entity);

        try {
            return $callback($entity);
        } finally {
            $this->set($previous);
        }
    }
}
