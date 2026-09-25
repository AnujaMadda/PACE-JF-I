<?php

namespace App\Domain\MasterData\Import;

use App\Domain\Core\Models\Entity;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-run state shared by a definition's validation and persistence:
 * the entity, the person importing, options, and code-to-id lookups
 * (always through the entity scope).
 */
final class ImportContext
{
    /** @var array<string, array<string, int|null>> */
    private array $ids = [];

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public readonly Entity $entity,
        public readonly User $user,
        public readonly array $options = [],
    ) {}

    /**
     * Id of the entity's record with this code, or null.
     *
     * @param  class-string<Model>  $model
     */
    public function idFor(string $model, ?string $code, string $column = 'code'): ?int
    {
        if ($code === null || $code === '') {
            return null;
        }

        $key = $model.'|'.$column;

        if (! array_key_exists($code, $this->ids[$key] ?? [])) {
            $id = $model::query()->where($column, $code)->value('id');
            $this->ids[$key][$code] = $id === null ? null : (int) $id;
        }

        return $this->ids[$key][$code];
    }

    /**
     * Id of a user with access to this entity, by email.
     */
    public function userIdFor(?string $email): ?int
    {
        if ($email === null || $email === '') {
            return null;
        }

        return $this->ids['user'][$email] ??= User::query()
            ->where('email', strtolower($email))
            ->whereHas('entities', fn ($q) => $q->whereKey($this->entity->getKey()))
            ->value('id');
    }

    /**
     * A closure validation rule: the code must exist in this entity.
     *
     * @param  class-string<Model>  $model
     */
    public function existsRule(string $model, string $label, string $column = 'code'): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($model, $label, $column): void {
            if ($value !== null && $value !== '' && $this->idFor($model, (string) $value, $column) === null) {
                $fail(__(':label ":value" does not exist in :entity.', ['label' => $label, 'value' => $value, 'entity' => $this->entity->code]));
            }
        };
    }

    public function userExistsRule(string $label): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($label): void {
            if ($value !== null && $value !== '' && $this->userIdFor((string) $value) === null) {
                $fail(__(':label ":value" is not a user of :entity.', ['label' => $label, 'value' => $value, 'entity' => $this->entity->code]));
            }
        };
    }
}
