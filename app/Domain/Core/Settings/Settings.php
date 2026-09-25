<?php

namespace App\Domain\Core\Settings;

use App\Domain\Core\Models\Entity;
use App\Domain\Core\Models\Setting;
use App\Domain\Core\Support\CurrentEntity;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Typed access to configurable settings. Resolution order for entity-scoped
 * keys: entity override, then group value, then the registry default.
 */
class Settings
{
    private const CACHE_KEY = 'pace.settings.v1';

    /** @var array<string, mixed>|null */
    private ?array $values = null;

    public function __construct(
        private readonly SettingsRegistry $registry,
        private readonly CurrentEntity $currentEntity,
    ) {}

    public function get(string $key, ?Entity $entity = null): mixed
    {
        $definition = $this->registry->get($key);
        $values = $this->values();

        if ($definition->scope === 'entity') {
            $entityId = $entity?->getKey() ?? $this->currentEntity->id();

            if ($entityId !== null && array_key_exists($entityId.':'.$key, $values)) {
                return $definition->cast($values[$entityId.':'.$key]);
            }
        }

        if (array_key_exists('0:'.$key, $values)) {
            return $definition->cast($values['0:'.$key]);
        }

        return $definition->default;
    }

    public function int(string $key, ?Entity $entity = null): int
    {
        return (int) $this->get($key, $entity);
    }

    public function bool(string $key, ?Entity $entity = null): bool
    {
        return (bool) $this->get($key, $entity);
    }

    /**
     * Store a value. Pass an entity only for entity-scoped keys.
     */
    public function set(string $key, mixed $value, ?Entity $entity = null, ?User $by = null): void
    {
        $definition = $this->registry->get($key);

        if ($entity !== null && $definition->scope !== 'entity') {
            throw new InvalidArgumentException("Setting [{$key}] is group-wide and cannot be overridden per entity.");
        }

        $value = $definition->cast($value);

        if ($definition->rules !== []) {
            Validator::make(['value' => $value], ['value' => $definition->rules])->validate();
        }

        $setting = Setting::query()
            ->where('key', $key)
            ->where('entity_id', $entity?->getKey())
            ->first() ?? new Setting(['key' => $key, 'entity_id' => $entity?->getKey()]);

        $old = $setting->exists ? $definition->cast($setting->value) : $this->get($key, $entity);

        $setting->fill(['value' => $value, 'updated_by' => $by?->getKey()])->save();

        $this->flush();

        if ($old !== $value) {
            activity('settings')
                ->causedBy($by)
                ->event('setting_changed')
                ->withProperties(['key' => $key, 'old' => $old, 'new' => $value, 'entity_id' => $entity?->getKey()])
                ->log("Setting {$key} changed");
        }
    }

    public function flush(): void
    {
        $this->values = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed> keyed by "{entityId or 0}:{key}"
     */
    private function values(): array
    {
        return $this->values ??= Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()
            ->get(['entity_id', 'key', 'value'])
            ->mapWithKeys(fn (Setting $s) => [($s->entity_id ?? 0).':'.$s->key => $s->value])
            ->all());
    }
}
